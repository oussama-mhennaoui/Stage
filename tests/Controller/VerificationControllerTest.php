<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class VerificationControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $userRepository;
    private $verifyEmailHelper;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = $this->client->getContainer()->get(UserRepository::class);
        $this->verifyEmailHelper = $this->client->getContainer()->get(VerifyEmailHelperInterface::class);
    }

    public function testVerificationNoticeRedirectsIfNotAuthenticated(): void
    {
        $this->client->request('GET', '/verification/notice');
        $this->assertResponseRedirects('/login');
    }

    public function testVerificationNoticeShowsForUnverifiedUser(): void
    {
        // Create a test user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Simulate authentication
        $this->client->loginUser($user);
        
        // Test the verification notice page
        $this->client->request('GET', '/verification/notice');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Verify Your Email Address');
    }

    public function testVerificationNoticeRedirectsIfVerified(): void
    {
        // Create a verified test user
        $user = new User();
        $user->setEmail('verified@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Simulate authentication
        $this->client->loginUser($user);
        
        // Should redirect to home if already verified
        $this->client->request('GET', '/verification/notice');
        $this->assertResponseRedirects('/');
    }

    public function testResendVerificationEmail(): void
    {
        // Create a test user
        $user = new User();
        $user->setEmail('resend@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Simulate authentication
        $this->client->loginUser($user);
        
        // Test resend verification email
        $this->client->request('GET', '/resend-verification');
        
        // Should redirect back to verification notice
        $this->assertResponseRedirects('/verification/notice');
        
        // Follow the redirect
        $this->client->followRedirect();
        
        // Check for success message
        $this->assertSelectorTextContains('.alert-success', 'A new verification link has been sent to your email address');
    }

    public function testVerifyEmailWithInvalidToken(): void
    {
        // Create a test user
        $user = new User();
        $user->setEmail('invalid-token@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Try to verify with an invalid token
        $this->client->request('GET', '/verify/email?token=invalid-token');
        
        // Should redirect to registration page with error
        $this->assertResponseRedirects('/register');
        
        // Follow the redirect
        $this->client->followRedirect();
        
        // Check for error message
        $this->assertSelectorTextContains('.alert-danger', 'The link to verify your email is invalid or has expired');
    }

    protected function tearDown(): void
    {
        // Clean up the database
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
        
        parent::tearDown();
    }
}
