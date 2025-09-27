<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\EmailVerificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\Exception\TransportException;

class EmailVerificationServiceTest extends TestCase
{
    private EmailVerificationService $emailVerificationService;
    private MockObject $verifyEmailHelper;
    private MockObject $mailer;
    private MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $this->emailVerificationService = new EmailVerificationService(
            $this->verifyEmailHelper,
            $this->mailer,
            $this->urlGenerator,
            'no-reply@example.com',
            'Test App'
        );
    }

    public function testSendVerificationEmailSuccess(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setId(123);

        // Mock the verification URL generation
        $verificationUrl = 'https://example.com/verify/email?token=abc123';
        $this->verifyEmailHelper->expects($this->once())
            ->method('generateSignature')
            ->willReturn((object)['getSignedUrl' => $verificationUrl]);

        // Mock the URL generator for the login route
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/login');

        // Expect the mailer to be called once with a TemplatedEmail
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) use ($user) {
                $this->assertInstanceOf(TemplatedEmail::class, $email);
                $this->assertEquals(['no-reply@example.com' => 'Test App'], $email->getFrom());
                $this->assertEquals([$user->getEmail()], array_keys($email->getTo()));
                $this->assertEquals('Please Confirm your Email', $email->getSubject());
                return true;
            }));

        // Execute the method
        $this->emailVerificationService->sendVerificationEmail($user);
    }

    public function testSendVerificationEmailFailure(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setId(123);

        // Mock the verification URL generation
        $this->verifyEmailHelper->expects($this->once())
            ->method('generateSignature')
            ->willReturn((object)['getSignedUrl' => 'https://example.com/verify/email?token=abc123']);

        // Mock the URL generator for the login route
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/login');

        // Make the mailer throw an exception
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new TransportException('Failed to send email'));

        // Expect an exception to be thrown
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to send email');

        // Execute the method
        $this->emailVerificationService->sendVerificationEmail($user);
    }

    public function testGetSignedUrl(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setId(123);

        $expectedUrl = 'https://example.com/verify/email?token=abc123';
        
        $this->verifyEmailHelper->expects($this->once())
            ->method('generateSignature')
            ->with(
                'app_verify_email',
                $user->getId(),
                $user->getEmail(),
                ['id' => $user->getId()]
            )
            ->willReturn((object)['getSignedUrl' => $expectedUrl]);

        $result = $this->emailVerificationService->getSignedUrl($user);
        $this->assertEquals($expectedUrl, $result);
    }

    public function testGetEmailTemplateVars(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPrenom('John');
        $user->setNom('Doe');

        $signedUrl = 'https://example.com/verify/email?token=abc123';
        $loginUrl = 'https://example.com/login';

        // Mock the URL generator for the login route
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($loginUrl);

        $vars = $this->emailVerificationService->getEmailTemplateVars($user, $signedUrl);

        $this->assertArrayHasKey('signedUrl', $vars);
        $this->assertArrayHasKey('expiresAtMessageKey', $vars);
        $this->assertArrayHasKey('expiresAtMessageData', $vars);
        $this->assertArrayHasKey('user', $vars);
        $this->assertArrayHasKey('loginUrl', $vars);
        
        $this->assertEquals($signedUrl, $vars['signedUrl']);
        $this->assertEquals($user, $vars['user']);
        $this->assertEquals($loginUrl, $vars['loginUrl']);
    }
}
