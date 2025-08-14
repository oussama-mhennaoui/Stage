<?php

namespace App\Form;

use App\Entity\Enum\TypeOffre;
use App\Entity\Offre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OffreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('description')
            ->add('typeOffre', EnumType::class, [
                'class' => TypeOffre::class,
                'choice_label' => function (TypeOffre $type) {
                    return ucfirst($type->value);
                },
                'placeholder' => 'Sélectionner un type',
                'required' => true,
                'attr' => [
                    'class' => 'offer-type-selector',
                    'data-controller' => 'offer-type'
                ]
            ])
            ->add('duree', null, [
                'label' => 'Durée (en mois)',
                'required' => false,
                'attr' => [
                    'class' => 'duration-field',
                    'min' => 1,
                    'max' => 12,
                    'data-offer-type-target' => 'durationField',
                    'data-action' => 'change->offer-type#toggleDurationField'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offre::class,
        ]);
    }
}
