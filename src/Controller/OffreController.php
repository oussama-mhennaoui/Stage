<?php

namespace App\Controller;

use App\Entity\Offre;
use App\Form\OffreType;
use App\Repository\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/offre')]
class OffreController extends AbstractController
{
    #[Route('/{id}/candidatures', name: 'app_offre_candidatures', methods: ['GET'])]
    public function candidatures(Offre $offre): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ENTREPRISE');
        // Ensure logged-in entreprise owns the offer
        if ($offre->getEntreprise() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter ces candidatures.');
        }
        return $this->render('offre/candidatures.html.twig', [
            'offre' => $offre,
            'candidatures' => $offre->getCandidatures(),
        ]);
    }

    #[Route('/public', name: 'app_offre_public', methods: ['GET'])]
    public function publicIndex(OffreRepository $offreRepository): Response
    {
        // No role restriction: open to students and graduates
        $offres = $offreRepository->findAll();
        // Collect unique types from offers
        $types = array_unique(array_map(function($offre) {
            return $offre->getTypeOffre() ? $offre->getTypeOffre()->value : null;
        }, $offres));
        $types = array_filter($types);
        sort($types);
        return $this->render('offre/public_index.html.twig', [
            'offres' => $offres,
            'types' => $types,
        ]);
    }

    #[Route('/public/{id}', name: 'app_offre_public_show', methods: ['GET'])]
    public function publicShow(Offre $offre): Response
    {
        // No role restriction: open to all, including unauthenticated users
        return $this->render('offre/public_show.html.twig', [
            'offre' => $offre,
        ]);
    }
    #[Route('/', name: 'app_offre_index', methods: ['GET'])]
    public function index(OffreRepository $offreRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ENTREPRISE');

        $entreprise = $this->getUser();

        return $this->render('offre/index.html.twig', [
            'offres' => $offreRepository->findBy(['entreprise' => $entreprise]),
        ]);
    }

    #[Route('/new', name: 'app_offre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ENTREPRISE');

        $offre = new Offre();
        $offre->setEntreprise($this->getUser());

        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($offre);
            $entityManager->flush();

            return $this->redirectToRoute('app_offre_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('offre/new.html.twig', [
            'offre' => $offre,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_offre_show', methods: ['GET'])]
    public function show(Offre $offre): Response
    {
        $this->denyAccessUnlessGranted('view', $offre);
        return $this->render('offre/show.html.twig', [
            'offre' => $offre,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_offre_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Offre $offre, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('edit', $offre);
        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_offre_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('offre/edit.html.twig', [
            'offre' => $offre,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_offre_delete', methods: ['POST'])]
    public function delete(Request $request, Offre $offre, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('delete', $offre);
        if ($this->isCsrfTokenValid('delete'.$offre->getId(), $request->request->get('_token'))) {
            $entityManager->remove($offre);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_offre_index', [], Response::HTTP_SEE_OTHER);
    }
}
