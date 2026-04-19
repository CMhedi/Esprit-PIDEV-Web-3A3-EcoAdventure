<?php

namespace App\Form;

use App\Entity\Reclamation;
use App\Entity\UserApp;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('type', ChoiceType::class, [
            'label' => 'Quel est votre problème ?',
            'choices'  => [
                'Problème Mot de passe' => 'Mot de passe',
                'Problème Séance' => 'Séance',
                'Problème Technique' => 'Technique',
                'Annulation Séance'  => 'Séance',
                'Paiement & Facture' => 'Paiement',
                'Autre'              => 'Autre',
            ],
            'attr' => ['class' => 'form-select mb-3']
        ])
        ->add('contenu', TextareaType::class, [
            'label' => 'Détails du problème',
            'attr' => [
                'class' => 'form-control',
                'rows' => 5,
                'placeholder' => 'Expliquez-nous dhoabt chnoua sarr...'
            ]
        ])


        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}
