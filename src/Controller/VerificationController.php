<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Psr\Log\LoggerInterface;

class VerificationController extends AbstractController
{
    private VerifyEmailHelperInterface $verifyEmailHelper;
    private EntityManagerInterface $entityManager;
    private EmailVerificationService $emailVerificationService;
    private LoggerInterface $logger;

    public function __construct(
        VerifyEmailHelperInterface $verifyEmailHelper,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService,
        LoggerInterface $logger
    ) {
        $this->verifyEmailHelper = $verifyEmailHelper;
        $this->entityManager = $entityManager;
        $this->emailVerificationService = $emailVerificationService;
        $this->logger = $logger;
    }

    /**
     * Verify user's email address
     */
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            $this->addFlash('error', 'Invalid verification link.');
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_register');
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmation(
                $request->getUri(),
                $user->getId(),
                $user->getEmail()
            );

            $user->setIsVerified(true);
            $this->entityManager->flush();

            // If user is not logged in, log them in automatically
            if (!$this->getUser()) {
                return $this->redirectToRoute('app_login', [
                    'email' => $user->getEmail(),
                    'verified' => 1,
                ]);
            }

            $this->addFlash('success', 'Your email address has been verified successfully!');
            return $this->redirectToRoute('app_home');

        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', $e->getReason());
            
            // If user is logged in but verification failed, redirect to verification notice
            if ($this->getUser()) {
                return $this->redirectToRoute('app_verification_notice');
            }
            
            return $this->redirectToRoute('app_register');
        }
    }

    /**
     * Show verification notice page
     */
    #[Route('/verification/notice', name: 'app_verification_notice')]
    public function verificationNotice(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var User $user */
        $user = $this->getUser();
        
        if ($user->isVerified()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/verification_notice.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * Resend verification email
     */
    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerificationEmail(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        // Validate CSRF token
        $submittedToken = $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('resend_verification', $submittedToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_verification_notice');
        }
        
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isVerified()) {
            $this->addFlash('info', 'Your email is already verified.');
            return $this->redirectToRoute('app_home');
        }

        try {
            // Log the attempt to resend verification email
            $this->logger->info('Attempting to resend verification email', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
            
            // Send the verification email
            $this->emailVerificationService->sendVerificationEmail($user);
            
            // Log success
            $this->logger->info('Successfully resent verification email', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
            
            $this->addFlash('success', 'A new verification link has been sent to your email address.');
        } catch (\Exception $e) {
            // Log the error with more details
            $this->logger->error('Failed to resend verification email', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->addFlash('error', 'Failed to send verification email. Please try again later. Error: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_verification_notice');
    }
}
