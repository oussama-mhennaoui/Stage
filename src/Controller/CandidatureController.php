<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Offre;
use App\Repository\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CandidatureController extends AbstractController
{
    #[Route('/candidature/new/{offreId}', name: 'app_candidature_new', methods: ['GET', 'POST'])]
    public function new(Request $request, $offreId, OffreRepository $offreRepository, EntityManagerInterface $entityManager): Response
    {
        $offre = $offreRepository->find($offreId);
        if (!$offre) {
            throw $this->createNotFoundException('Offre not found');
        }
        if (!$this->getUser() || !($this->isGranted('ROLE_ETUDIANT') || $this->isGranted('ROLE_DIPLOME'))) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à postuler à cette offre.');
        }
        $candidature = new \App\Entity\Candidature();
        $form = $this->createForm(\App\Form\CandidatureType::class, $candidature);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $cvFile = $form->get('cv')->getData();
            $lettreFile = $form->get('lettreMotivation')->getData();
            if ($cvFile) {
                $this->addFlash('success', 'DEBUG: CV file detected: ' . $cvFile->getClientOriginalName());
            } else {
                $this->addFlash('error', 'DEBUG: CV file is NOT present in request.');
            }
            if ($lettreFile) {
                $this->addFlash('success', 'DEBUG: Lettre file detected: ' . $lettreFile->getClientOriginalName());
            } else {
                $this->addFlash('info', 'DEBUG: Lettre file is NOT present in request.');
            }

            if ($form->isValid()) {
                if ($cvFile) {
                    $cvFilename = uniqid().'_cv.'.$cvFile->guessExtension();
                    try {
                        $cvFile->move($this->getParameter('kernel.project_dir').'/public/uploads/candidatures', $cvFilename);
                        $candidature->setCv('/uploads/candidatures/' . $cvFilename);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur lors de l\'upload du CV : '.$e->getMessage());
                        return $this->render('candidature/new.html.twig', [
                            'form' => $form->createView(),
                            'offre' => $offre,
                        ]);
                    }
                }
                if ($lettreFile) {
                    $lettreFilename = uniqid().'_lettre.'.$lettreFile->guessExtension();
                    try {
                        $lettreFile->move($this->getParameter('kernel.project_dir').'/public/uploads/candidatures', $lettreFilename);
                        $candidature->setLettreMotivation('/uploads/candidatures/' . $lettreFilename);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur lors de l\'upload de la lettre : '.$e->getMessage());
                        return $this->render('candidature/new.html.twig', [
                            'form' => $form->createView(),
                            'offre' => $offre,
                        ]);
                    }
                }
                $candidature->setEtatCandidature('en cours');
                $candidature->setOffre($offre);
                $candidature->setCandidat($this->getUser());
                $entityManager->persist($candidature);
                $entityManager->flush();
                $this->addFlash('success', 'Votre candidature a été soumise avec succès !');
                return $this->redirectToRoute('app_offre_public');
            } else {
                // Collect and show all form errors for debugging
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                $this->addFlash('error', 'Erreur lors de la soumission du formulaire. Veuillez vérifier les champs et réessayer.');
                return $this->render('candidature/new.html.twig', [
                    'offre' => $offre,
                    'form' => $form->createView()
                ]);
            }
        }
            // Collect and show all form errors for debugging
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            $this->addFlash('error', 'Erreur lors de la soumission du formulaire. Veuillez vérifier les champs et réessayer.');

        return $this->render('candidature/new.html.twig', [
            'offre' => $offre,
            'form' => $form->createView()
        ]);
    }

    #[Route('/candidature/{id}/accept', name: 'app_candidature_accept', methods: ['POST'])]
    public function accept(Candidature $candidature, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ENTREPRISE');
        if ($candidature->getOffre()->getEntreprise() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        $candidature->setEtatCandidature('acceptée');
        $entityManager->flush();
        $this->addFlash('success', 'Candidature acceptée.');
        return $this->redirectToRoute('app_offre_candidatures', ['id' => $candidature->getOffre()->getId()]);
    }

    #[Route('/candidature/{id}/reject', name: 'app_candidature_reject', methods: ['POST'])]
    public function reject(Candidature $candidature, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ENTREPRISE');
        if ($candidature->getOffre()->getEntreprise() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        $candidature->setEtatCandidature('rejetée');
        $entityManager->flush();
        $this->addFlash('success', 'Candidature rejetée.');
        return $this->redirectToRoute('app_offre_candidatures', ['id' => $candidature->getOffre()->getId()]);
    }
}
