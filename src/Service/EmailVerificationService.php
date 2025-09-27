<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class EmailVerificationService
{
    private VerifyEmailHelperInterface $verifyEmailHelper;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private string $appName;
    private string $appEmail;

    public function __construct(
        VerifyEmailHelperInterface $verifyEmailHelper,
        MailerInterface $mailer,
        LoggerInterface $logger,
        string $appName = 'Your Application',
        string $appEmail = 'no-reply@yourdomain.com'
    ) {
        $this->verifyEmailHelper = $verifyEmailHelper;
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->appName = $appName;
        $this->appEmail = $appEmail;
    }

    /**
     * @throws TransportExceptionInterface When an error occurs while sending the email
     */
    public function sendVerificationEmail(User $user, string $routeName = 'app_verify_email'): void
    {
        try {
            // Log the start of the email sending process with user details
            $this->logger->info('Starting email verification process', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'user_class' => get_class($user),
                'user_type' => $user->getUserType() ?? 'unknown'
            ]);
            
            // Ensure we have a valid user ID
            if ($user->getId() === null) {
                $errorMsg = 'Cannot send verification email: User has not been persisted yet';
                $this->logger->error($errorMsg, [
                    'email' => $user->getEmail(),
                    'user_class' => get_class($user)
                ]);
                throw new \RuntimeException($errorMsg);
            }
            
            $userId = $user->getId();
            
            // Log that we have a valid user ID
            $this->logger->debug('User has valid ID', [
                'user_id' => $userId,
                'email' => $user->getEmail()
            ]);

            // Generate the verification URL with the user's ID
            $signatureComponents = $this->verifyEmailHelper->generateSignature(
                $routeName,
                (string) $userId,
                $user->getEmail(),
                ['id' => $userId]
            );

            $signedUrl = $signatureComponents->getSignedUrl();
            
            $this->logger->info('Generated verification URL', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'verification_url' => $signedUrl,
                'expires_at' => $signatureComponents->getExpiresAt()->format('Y-m-d H:i:s')
            ]);

            // Get the recipient name (fallback to email if full name is not available)
            $recipientName = method_exists($user, 'getFullName') 
                ? $user->getFullName() 
                : $user->getEmail();
                
            $this->logger->debug('Sending verification email to', [
                'email' => $user->getEmail(),
                'name' => $recipientName
            ]);
            
            // Create the email with proper encoding and content type
            $email = (new TemplatedEmail())
                ->from(new Address($this->appEmail, $this->appName))
                ->to(new Address($user->getEmail(), $recipientName))
                ->subject('Veuillez vérifier votre adresse email')
                ->htmlTemplate('email/verification_email.html.twig')
                ->context([
                    'signedUrl' => $signedUrl,
                    'expiresAt' => $signatureComponents->getExpiresAt(),
                    'user' => $user,
                ]);

            // Add a plain text version for email clients that don't support HTML
            $email->text(
                sprintf(
                    "Bonjour %s,\n\nMerci de vous être inscrit. Veuillez cliquer sur le lien suivant pour vérifier votre adresse email :\n\n%s\n\nSi vous n'avez pas créé de compte, vous pouvez ignorer cet email.\n\nCordialement,\nL'équipe %s",
                    $user->getPrenom(),
                    $signedUrl,
                    $this->appName
                )
            );

            // Log the email details before sending
            $this->logger->debug('Sending verification email', [
                'from' => $this->appEmail,
                'to' => $user->getEmail(),
                'subject' => 'Veuillez vérifier votre adresse email'
            ]);

            // Send the email
            $this->mailer->send($email);
            
            $this->logger->info('Verification email sent successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
            
        } catch (VerifyEmailExceptionInterface $e) {
            $errorMessage = sprintf(
                'Failed to generate verification URL: %s',
                $e->getMessage()
            );
            $this->logger->error($errorMessage, [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException($errorMessage, 0, $e);
        } catch (TransportExceptionInterface $e) {
            $errorMessage = sprintf(
                'Failed to send verification email: %s',
                $e->getMessage()
            );
            $this->logger->error($errorMessage, [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException($errorMessage, 0, $e);
        } catch (\Exception $e) {
            $errorMessage = sprintf(
                'Unexpected error sending verification email: %s',
                $e->getMessage()
            );
            $this->logger->error($errorMessage, [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException($errorMessage, 0, $e);
        }
    }
}
