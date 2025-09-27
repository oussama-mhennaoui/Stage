<?php

namespace App\Controller;

use App\Entity\Encadrement;
use App\Form\EncadrementType;
use App\Repository\EncadrementRepository;
use App\Repository\EnseignantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/encadrement')]
class EncadrementController extends AbstractController
{
    #[Route('/', name: 'app_encadrement_index', methods: ['GET'])]
    public function index(EncadrementRepository $encadrementRepository): Response
    {
        $user = $this->getUser();
        
        if ($this->isGranted('ROLE_ETUDIANT')) {
            return $this->redirectToRoute('app_enseignant_list');
        } elseif ($this->isGranted('ROLE_ENSEIGNANT')) {
            return $this->redirectToRoute('app_encadrement_teacher_requests');
        }
        
        return $this->redirectToRoute('app_home');
    }

    #[Route('/mes-demandes', name: 'app_encadrement_mes_demandes', methods: ['GET'])]
    public function indexEncadrement(EncadrementRepository $encadrementRepository): Response
    {
        $user = $this->getUser();
        
        if ($this->isGranted('ROLE_ETUDIANT')) {
            $encadrements = $encadrementRepository->findBy(['etudiant' => $user]);
        } elseif ($this->isGranted('ROLE_ENSEIGNANT')) {
            $encadrements = $encadrementRepository->findBy(['enseignant' => $user]);
        } else {
            $encadrements = $encadrementRepository->findAll();
        }

        return $this->render('encadrement/index.html.twig', [
            'encadrements' => $encadrements,
        ]);
    }
    
    #[Route('/teacher/requests', name: 'app_encadrement_teacher_requests', methods: ['GET'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function teacherRequests(EncadrementRepository $encadrementRepository): Response
    {
        $user = $this->getUser();
        $requests = $encadrementRepository->findBy(
            ['enseignant' => $user],
            ['status' => 'ASC', 'createdAt' => 'DESC']
        );
        
        // Group requests by status for better organization
        $groupedRequests = [
            'pending' => [],
            'accepted' => [],
            'rejected' => []
        ];
        
        foreach ($requests as $request) {
            $groupedRequests[$request->getStatus()][] = $request;
        }
        
        return $this->render('encadrement/teacher_requests.html.twig', [
            'requests' => $requests,
            'groupedRequests' => $groupedRequests
        ]);
    }

    #[Route('/new/{enseignantId}', name: 'app_encadrement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        EnseignantRepository $enseignantRepository,
        int $enseignantId,
        SluggerInterface $slugger
    ): Response {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $etudiant = $this->getUser();
        $enseignant = $enseignantRepository->find($enseignantId);
        
        if (!$enseignant) {
            throw $this->createNotFoundException('Enseignant not found');
        }

        // Check if a request already exists
        $existingRequest = $entityManager->getRepository(Encadrement::class)->findOneBy([
            'etudiant' => $etudiant,
            'enseignant' => $enseignant,
        ]);

        if ($existingRequest) {
            $this->addFlash('warning', 'Vous avez déjà une demande d\'encadrement en attente avec cet enseignant.');
            return $this->redirectToRoute('app_encadrement_index');
        }

        $encadrement = new Encadrement();
        $encadrement->setEtudiant($etudiant);
        $encadrement->setEnseignant($enseignant);
        
        $form = $this->createForm(EncadrementType::class, $encadrement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $cvFile */
            $cvFile = $form->get('cvFile')->getData();
            
            if ($cvFile) {
                try {
                    $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();
                    
                    // Create the uploads/encadrement directory if it doesn't exist
                    $uploadPath = $uploadDir . '/encadrement/cv';
                    if (!file_exists($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    
                    $cvFile->move($uploadPath, $newFilename);
                    $encadrement->setCvName($newFilename);
                    
                    $entityManager->persist($encadrement);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre demande d\'encadrement a été envoyée avec succès.');
                    return $this->redirectToRoute('app_encadrement_index');
                    
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur s\'est produite lors du téléchargement du fichier: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Veuillez télécharger un CV.');
            }
        }

        return $this->render('encadrement/new.html.twig', [
            'form' => $form->createView(),
            'enseignant' => $enseignant,
        ]);
    }

    #[Route('/{id}/accept', name: 'app_encadrement_accept', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function accept(
        Request $request, 
        Encadrement $encadrement, 
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('accept'.$encadrement->getId(), $request->request->get('_token'))) {
            $encadrement->setStatus(Encadrement::STATUS_ACCEPTED);
            $entityManager->flush();
            
            $this->addFlash('success', 'La demande d\'encadrement a été acceptée.');
        }

        return $this->redirectToRoute('app_encadrement_index');
    }

    #[Route('/{id}/reject', name: 'app_encadrement_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function reject(
        Request $request, 
        Encadrement $encadrement, 
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('reject'.$encadrement->getId(), $request->request->get('_token'))) {
            $encadrement->setStatus(Encadrement::STATUS_REJECTED);
            $entityManager->flush();
            
            $this->addFlash('warning', 'La demande d\'encadrement a été rejetée.');
        }

        return $this->redirectToRoute('app_encadrement_index');
    }
    
    #[Route('/{id}/update-status', name: 'app_encadrement_update_status', methods: ['POST'])]
    #[IsGranted('ROLE_ENSEIGNANT')]
    public function updateStatus(
        Request $request,
        Encadrement $encadrement,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Check if the current user is the teacher of this encadrement
        if ($encadrement->getEnseignant() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $status = $request->request->get('status');
        
        if (!in_array($status, ['accepted', 'rejected'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
        }
        
        $encadrement->setStatus($status);
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Status updated successfully',
            'newStatus' => $status,
            'statusLabel' => $status === 'accepted' ? 'Accepté' : 'Rejeté'
        ]);
    }

    #[Route('/{id}', name: 'app_encadrement_show', methods: ['GET'])]
    public function show(Encadrement $encadrement): Response
    {
        $this->denyAccessUnlessGranted('view', $encadrement);
        
        return $this->render('encadrement/show.html.twig', [
            'encadrement' => $encadrement,
        ]);
    }
    
    #[Route('/{id}/download-cv', name: 'app_encadrement_download_cv', methods: ['GET'])]
    public function downloadCv(Encadrement $encadrement): Response
    {
        $user = $this->getUser();
        
        // Check if the current user is either the student or the teacher of this encadrement
        if ($user !== $encadrement->getEtudiant() && $user !== $encadrement->getEnseignant()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à ce CV');
        }
        
        if (!$encadrement->getCvName()) {
            throw $this->createNotFoundException('CV non trouvé');
        }
        
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/encadrement/cv';
        $filePath = $uploadDir . '/' . $encadrement->getCvName();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier CV est introuvable');
        }
        
        $file = new File($filePath);
        
        // Force download instead of inline display for better security
        return $this->file($file, null, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
    
    #[Route('/mes-demandes', name: 'app_encadrement_student_applications', methods: ['GET'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function studentApplications(EncadrementRepository $encadrementRepository): Response
    {
        $user = $this->getUser();
        $applications = $encadrementRepository->findBy(
            ['etudiant' => $user],
            ['createdAt' => 'DESC']
        );
        
        // Group applications by status for better organization
        $groupedApplications = [
            'pending' => [],
            'accepted' => [],
            'rejected' => []
        ];
        
        foreach ($applications as $application) {
            $groupedApplications[$application->getStatus()][] = $application;
        }
        
        return $this->render('encadrement/student_applications.html.twig', [
            'groupedApplications' => $groupedApplications,
            'applications' => $applications
        ]);
    }
}
