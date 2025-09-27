<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestEmailController extends AbstractController
{
    private EmailVerificationService $emailVerificationService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EmailVerificationService $emailVerificationService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->emailVerificationService = $emailVerificationService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * @Route("/test/email/{email}", name="test_email")
     */
    public function testEmail(string $email): Response
    {
        // Check if the user exists
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if (!$user) {
            return new Response(sprintf('No user found with email: %s', $email), 404);
        }

        try {
            $this->emailVerificationService->sendVerificationEmail($user);
            return new Response(sprintf('Test email sent to %s', $email));
        } catch (\Exception $e) {
            $this->logger->error('Failed to send test email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return new Response(sprintf('Failed to send test email: %s', $e->getMessage()), 500);
        }
    }
}
