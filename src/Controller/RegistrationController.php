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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\Form\FormError;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
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
                
                // Persister l'utilisateur
                $entityManager->persist($user);
                $entityManager->flush();

                // Send email verification after successful registration
                try {
                    $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                        (new TemplatedEmail())
                            ->from(new Address('mailer@mailer.de', 'mailer boot'))
                            ->to((string) $user->getEmail())
                            ->subject('Veuillez confirmer votre email')
                            ->htmlTemplate('registration/confirmation_email.html.twig')
                    );
                } catch (\Exception $e) {
                    // Log the error but continue with registration
                    $this->addFlash('warning', 'Une erreur est survenue lors de l\'envoi de l\'email de vérification. Veuillez vérifier votre email manuellement.');
                }

                return $security->login($user, AppAuthenticator::class, 'main');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
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
        return match($userType) {
            'etudiant' => new Etudiant(),
            'diplome' => new Diplome(),
            'enseignant' => new Enseignant(),
            'entreprise' => new Entreprise(),
            default => new User(),
        };
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
                 
                 if ($nomEntreprise && !empty($nomEntreprise)) {
                     $user->setNomEntreprise($nomEntreprise);
                 }
                 if ($secteurActivite && !empty($secteurActivite)) {
                     $user->setSecteurActivite($secteurActivite);
                 }
                 if ($adresse && !empty($adresse)) {
                     $user->setAdresse($adresse);
                 }
                 if ($siteWeb && !empty($siteWeb)) {
                     $user->setSiteWeb($siteWeb);
                 }
                 if ($responsableRH && !empty($responsableRH)) {
                     $user->setResponsableRH($responsableRH);
                 }
                 break;
         }
     }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_admin');
    }
}
