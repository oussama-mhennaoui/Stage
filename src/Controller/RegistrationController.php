<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Etudiant;
use App\Entity\Diplome;
use App\Entity\Enseignant;
use App\Entity\Entreprise;
use App\Form\RegistrationFormType;
use App\Security\AppAuthenticator;
use App\Security\EmailVerifier;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\Form\FormError;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;
    private EmailVerificationService $emailVerificationService;
    private LoggerInterface $logger;

    public function __construct(
        EmailVerifier $emailVerifier,
        EmailVerificationService $emailVerificationService,
        LoggerInterface $logger
    ) {
        $this->emailVerifier = $emailVerifier;
        $this->emailVerificationService = $emailVerificationService;
        $this->logger = $logger;
    }

    #[Route('/test', name: 'app_test')]
    public function test(): Response
    {
        return new Response('Test route works!');
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        // Créer un formulaire d'inscription sans lier d'entité pour l'instant
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);
        $userType = null; // Initialize userType

        if ($form->isSubmitted()) {
            // Validation personnalisée pour les champs conditionnels
            $userType = $form->get('userType')->getData();
            $this->validateConditionalFields($form, $userType);
            
            if ($form->isValid()) {
                // Récupérer le type d'utilisateur sélectionné
                
                // Créer l'entité appropriée en fonction du type d'utilisateur
                $user = $this->createUserByType($userType);
                
                // Définir les propriétés communes à tous les utilisateurs
                $user->setNom($form->get('Nom')->getData());
                $user->setPrenom($form->get('prenom')->getData());
                $user->setNumTel($form->get('numTel')->getData());
                $user->setEmail($form->get('email')->getData());
                
                // Set appropriate roles based on user type
                switch ($userType) {
                    case 'etudiant':
                        $user->setRoles(['ROLE_ETUDIANT']);
                        break;
                    case 'diplome':
                        $user->setRoles(['ROLE_DIPLOME']);
                        break;
                    case 'enseignant':
                        $user->setRoles(['ROLE_ENSEIGNANT']);
                        break;
                    case 'entreprise':
                        $user->setRoles(['ROLE_ENTREPRISE']);
                        break;
                    default:
                        $user->setRoles(['ROLE_USER']);
                }
                
                /** @var string $plainPassword */
                $plainPassword = $form->get('plainPassword')->getData();
                // Encoder le mot de passe
                $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
                
                // Définir les propriétés spécifiques au type d'utilisateur
                $this->setUserTypeSpecificProperties($user, $form, $userType);
                
                // First, persist the user to get an ID
                $entityManager->persist($user);
                $entityManager->flush();
                
                // Ensure the user has been properly persisted
                if ($user->getId() === null) {
                    $this->logger->error('Failed to persist user', [
                        'email' => $user->getEmail(),
                        'user_type' => $userType
                    ]);
                    throw new \RuntimeException('Failed to create user account. Please try again.');
                }

                // Log the successful persistence
                $this->logger->info('User successfully persisted', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'user_type' => $userType
                ]);

                // Send email verification after successful registration
                try {
                    $this->logger->info('Attempting to send verification email', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'user_type' => $userType
                    ]);

                    $this->emailVerificationService->sendVerificationEmail($user);
                    
                    // Add a success message
                    $this->addFlash('success', 'Inscription réussie ! Veuillez vérifier votre email pour activer votre compte.');
                    
                    // Log the successful registration
                    $this->logger->info('New user registered successfully', [
                        'email' => $user->getEmail(),
                        'user_type' => $userType,
                        'user_id' => $user->getId()
                    ]);
                    
                    // Redirect to the verification notice page
                    return $this->redirectToRoute('app_verification_notice');
                    
                } catch (TransportExceptionInterface $e) {
                    // Log the error with more details
                    $this->logger->error('Failed to send verification email', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Still mark the user as verified since we can't send the email
                    $user->setIsVerified(true);
                    $entityManager->persist($user);
                    $entityManager->flush();
                    
                    $this->logger->warning('Marked user as verified due to email sending failure', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail()
                    ]);
                    
                    // Add a success message but inform about the email issue
                    $this->addFlash('success', 'Votre compte a été créé avec succès, mais nous n\'avons pas pu vous envoyer l\'email de vérification. Vous pouvez vous connecter directement.');
                    
                    // Redirect to login page
                    return $this->redirectToRoute('app_login');
                    
                } catch (\Exception $e) {
                    // Log any other unexpected errors with full details
                    $this->logger->error('Unexpected error during registration', [
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Mark the user as verified as a fallback
                    try {
                        $user->setIsVerified(true);
                        $entityManager->persist($user);
                        $entityManager->flush();
                        
                        $this->logger->warning('Marked user as verified due to unexpected error', [
                            'user_id' => $user->getId(),
                            'email' => $user->getEmail()
                        ]);
                        
                        $this->addFlash('success', 'Votre compte a été créé avec succès. Vous pouvez vous connecter.');
                        return $this->redirectToRoute('app_login');
                        
                    } catch (\Exception $innerException) {
                        $this->logger->critical('Failed to mark user as verified', [
                            'user_id' => $user->getId(),
                            'error' => $innerException->getMessage(),
                            'trace' => $innerException->getTraceAsString()
                        ]);
                        
                        // If we can't even save the user, show an error
                        $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la création de votre compte. Veuillez réessayer ou contacter le support.');
                        
                        return $this->render('registration/register.html.twig', [
                            'registrationForm' => $form,
                            'userType' => $userType
                        ]);
                    }
                }
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'userType' => $userType // Pass userType to the template
        ]);
    }
    
    /**
     * Valide les champs conditionnels en fonction du type d'utilisateur sélectionné
     */
    private function validateConditionalFields($form, string $userType): void
    {
        switch ($userType) {
            case 'etudiant':
                // Pour les étudiants, niveau et filière sont requis
                if (empty($form->get('niveau')->getData())) {
                    $form->get('niveau')->addError(new FormError('Le niveau d\'études est requis pour les étudiants'));
                }
                if (empty($form->get('filiere')->getData())) {
                    $form->get('filiere')->addError(new FormError('La filière est requise pour les étudiants'));
                }
                break;
                
            case 'diplome':
                // Pour les diplômés, filière et diplôme sont requis
                if (empty($form->get('filiere')->getData())) {
                    $form->get('filiere')->addError(new FormError('La filière est requise pour les diplômés'));
                }
                if (empty($form->get('diplome')->getData())) {
                    $form->get('diplome')->addError(new FormError('Le diplôme obtenu est requis pour les diplômés'));
                }
                break;
                
            case 'enseignant':
                // Pour les enseignants, spécialité et département sont requis
                if (empty($form->get('specialite')->getData())) {
                    $form->get('specialite')->addError(new FormError('La spécialité est requise pour les enseignants'));
                }
                if (empty($form->get('departement')->getData())) {
                    $form->get('departement')->addError(new FormError('Le département est requis pour les enseignants'));
                }
                break;
                
            case 'entreprise':
                // Pour les entreprises, nom, secteur, adresse et responsable RH sont requis
                if (empty($form->get('nomEntreprise')->getData())) {
                    $form->get('nomEntreprise')->addError(new FormError('Le nom de l\'entreprise est requis'));
                }
                if (empty($form->get('secteurActivite')->getData())) {
                    $form->get('secteurActivite')->addError(new FormError('Le secteur d\'activité est requis'));
                }
                if (empty($form->get('adresse')->getData())) {
                    $form->get('adresse')->addError(new FormError('L\'adresse est requise'));
                }
                if (empty($form->get('responsableRH')->getData())) {
                    $form->get('responsableRH')->addError(new FormError('Le responsable RH est requis'));
                }
                break;
        }
    }
    
    /**
     * Crée une instance d'utilisateur en fonction du type sélectionné
     */
    private function createUserByType(string $userType): User
    {
        $this->logger->info('Creating user of type', ['user_type' => $userType]);
        
        $user = match($userType) {
            'etudiant' => new Etudiant(),
            'diplome' => new Diplome(),
            'enseignant' => new Enseignant(),
            'entreprise' => new Entreprise(),
            default => new User(),
        };
        
        // Set the creation timestamp
        $user->setCreatedAt(new \DateTimeImmutable());
        
        // Log the created user class and ID (should be null at this point)
        $this->logger->info('Created user instance', [
            'class' => get_class($user),
            'id' => $user->getId()
        ]);
        
        return $user;
    }
    
    /**
      * Définit les propriétés spécifiques au type d'utilisateur
      */
     private function setUserTypeSpecificProperties(User $user, $form, string $userType): void
     {
         switch ($userType) {
             case 'etudiant':
                 /** @var Etudiant $user */
                 $niveau = $form->get('niveau')->getData();
                 $filiere = $form->get('filiere')->getData();
                 
                 if ($niveau && !empty($niveau)) {
                     $user->setNiveau($niveau);
                 }
                 if ($filiere && !empty($filiere)) {
                     $user->setFiliere($filiere);
                 }
                 break;
                 
             case 'diplome':
                 /** @var Diplome $user */
                 $filiere = $form->get('filiere')->getData();
                 $diplome = $form->get('diplome')->getData();
                 
                 if ($filiere && !empty($filiere)) {
                     $user->setFiliere($filiere);
                 }
                 if ($diplome && !empty($diplome)) {
                     $user->setDiplome($diplome);
                 }
                 break;
                 
             case 'enseignant':
                 /** @var Enseignant $user */
                 $specialite = $form->get('specialite')->getData();
                 $departement = $form->get('departement')->getData();
                 
                 if ($specialite && !empty($specialite)) {
                     $user->setSpecialite($specialite);
                 }
                 if ($departement && !empty($departement)) {
                     $user->setDepartement($departement);
                 }
                 $user->setIsResponsablePFE($form->get('isResponsablePFE')->getData() ?? false);
                 break;
                 
             case 'entreprise':
                 /** @var Entreprise $user */
                 $nomEntreprise = $form->get('nomEntreprise')->getData();
                 $secteurActivite = $form->get('secteurActivite')->getData();
                 $adresse = $form->get('adresse')->getData();
                 $siteWeb = $form->get('siteWeb')->getData();
                 $responsableRH = $form->get('responsableRH')->getData();
                 
                 // Ensure we have a proper Entreprise instance
                 if (!$user instanceof Entreprise) {
                     throw new \RuntimeException('Expected Entreprise instance');
                 }
                 
                 $user->setNomEntreprise($nomEntreprise);
                 $user->setSecteurActivite($secteurActivite);
                 $user->setAdresse($adresse);
                 
                 // Site web is optional
                 if ($siteWeb) {
                     $user->setSiteWeb($siteWeb);
                 }
                 
                 $user->setResponsableRH($responsableRH);
                 
                 // Log the properties being set
                 $this->logger->info('Setting Entreprise properties', [
                     'nom_entreprise' => $nomEntreprise,
                     'secteur_activite' => $secteurActivite,
                     'adresse' => $adresse,
                     'site_web' => $siteWeb,
                     'responsable_rh' => $responsableRH
                 ]);
                 break;
        }
    }

    /**
     * Verify user's email address
     */
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    {
        $id = $request->query->get('id');
        
        // If no user ID is provided, redirect to registration
        if ($id === null) {
            $this->addFlash('error', 'Invalid verification link.');
            return $this->redirectToRoute('app_register');
        }
        
        // Get the user from the ID in the URL
        $user = $entityManager->getRepository(User::class)->find($id);
        
        // If no user is found, redirect to registration
        if ($user === null) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_register');
        }
        
        // Validate email confirmation
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
            
            // Mark the user as verified
            $user->setIsVerified(true);
            $entityManager->flush();
            
            // Log the user in automatically after verification
            $this->addFlash('success', 'Your email address has been verified. You are now logged in.');
            
            // Redirect to the home page or dashboard
            return $this->redirectToRoute('app_home');
            
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            
            // If the user is logged in but the verification link is invalid
            if ($this->getUser()) {
                return $this->redirectToRoute('app_verification_notice');
            }
            
            return $this->redirectToRoute('app_register');
        }
    }
}
