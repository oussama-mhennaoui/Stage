<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // If user is already logged in, redirect them to their dashboard
        if ($this->getUser()) {
            return $this->redirectBasedOnRole($this->getUser());
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        
        // Clear the session to prevent any stale data
        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->invalidate();
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error
        ]);
    }
    
    private function redirectBasedOnRole($user)
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        } elseif ($this->isGranted('ROLE_ENTREPRISE')) {
            return $this->redirectToRoute('app_offre_index');
        } elseif ($this->isGranted('ROLE_ETUDIANT') || $this->isGranted('ROLE_DIPLOME')) {
            return $this->redirectToRoute('app_home');
        }
        
        return $this->redirectToRoute('app_home');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
