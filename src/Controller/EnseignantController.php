<?php

namespace App\Controller;

use App\Repository\EnseignantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/enseignant')]
class EnseignantController extends AbstractController
{
    #[Route('/list', name: 'app_enseignant_list', methods: ['GET'])]
    #[IsGranted('ROLE_ETUDIANT')]
    public function list(EnseignantRepository $enseignantRepository): Response
    {
        return $this->render('enseignant/list.html.twig', [
            'enseignants' => $enseignantRepository->findAll(),
        ]);
    }
}
