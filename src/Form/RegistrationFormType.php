<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Etudiant;
use App\Entity\Diplome;
use App\Entity\Enseignant;
use App\Entity\Entreprise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('userType', ChoiceType::class, [
                'mapped' => false,
                'label' => 'Je suis un',
                'choices' => [
                    'Étudiant' => 'etudiant',
                    'Diplômé' => 'diplome',
                    'Enseignant' => 'enseignant',
                    'Entreprise' => 'entreprise',
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner votre profil',
                    ]),
                ],
            ])
            ->add('Nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre nom',
                    ]),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre prénom',
                    ]),
                ],
            ])
            ->add('numTel', TextType::class, [
                'label' => 'Numéro de téléphone',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre numéro de téléphone',
                    ]),
                ],
            ])
            ->add('email', null, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre email',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe',
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
            ])
            // Champs pour Étudiant - sans contraintes NotBlank car conditionnels
            ->add('niveau', TextType::class, [
                'mapped' => false,
                'label' => 'Niveau d\'études',
                'required' => false,
            ])
            ->add('filiere', TextType::class, [
                'mapped' => false,
                'label' => 'Filière',
                'required' => false,
            ])
            // Champs pour Diplômé - sans contraintes NotBlank car conditionnels
            ->add('diplome', TextType::class, [
                'mapped' => false,
                'label' => 'Diplôme obtenu',
                'required' => false,
            ])
            // Champs pour Enseignant - sans contraintes NotBlank car conditionnels
            ->add('specialite', TextType::class, [
                'mapped' => false,
                'label' => 'Spécialité',
                'required' => false,
            ])
            ->add('departement', TextType::class, [
                'mapped' => false,
                'label' => 'Département',
                'required' => false,
            ])
            ->add('isResponsablePFE', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Je suis responsable PFE',
                'required' => false,
            ])
            // Champs pour Entreprise - sans contraintes NotBlank car conditionnels
            ->add('nomEntreprise', TextType::class, [
                'mapped' => false,
                'label' => 'Nom de l\'entreprise',
                'required' => false,
            ])
            ->add('secteurActivite', TextType::class, [
                'mapped' => false,
                'label' => 'Secteur d\'activité',
                'required' => false,
            ])
            ->add('adresse', TextType::class, [
                'mapped' => false,
                'label' => 'Adresse',
                'required' => false,
            ])
            ->add('siteWeb', TextType::class, [
                'mapped' => false,
                'label' => 'Site web',
                'required' => false,
            ])
            ->add('responsableRH', TextType::class, [
                'mapped' => false,
                'label' => 'Responsable RH',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
