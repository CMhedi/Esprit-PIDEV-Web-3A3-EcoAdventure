<?php

namespace App\Form;

use App\Entity\Pack;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du pack',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Pack Aventure Premium'
                ]
            ])
            ->add('typePack', ChoiceType::class, [
                'label' => 'Type du pack',
                'choices' => [
                    'Sportif' => 'Sportif',
                    'Loisir' => 'Loisir',
                    'Famille' => 'Famille',
                    'Premium' => 'Premium',
                    'Extrême' => 'Extrême'
                ],
                'placeholder' => 'Sélectionner un type',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('prixBase', MoneyType::class, [
                'label' => 'Prix de base',
                'currency' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 150'
                ]
            ])
            ->add('reduction', MoneyType::class, [
                'label' => 'Réduction',
                'currency' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 20'
                ]
            ])
            ->add('nbActivitesMax', IntegerType::class, [
                'label' => 'Nombre maximum d’activités',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 5',
                    'min' => 1,
                    'max' => 100
                ]
            ])
            ->add('statutPack', ChoiceType::class, [
                'label' => 'Statut du pack',
                'choices' => [
                    'Actif' => 'Actif',
                    'Inactif' => 'Inactif',
                    'Disponible' => 'Disponible'
                ],
                'placeholder' => 'Sélectionner un statut',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer le pack',
                'attr' => [
                    'class' => 'btn btn-pack-save'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Pack::class,
        ]);
    }
}