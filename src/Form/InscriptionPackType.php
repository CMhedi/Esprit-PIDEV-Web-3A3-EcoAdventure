<?php

namespace App\Form;

use App\Entity\Inscription;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class InscriptionPackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_user', TextType::class, [
                'label' => 'Nom complet',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez votre nom complet'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom complet est obligatoire.'
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne doit pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Confirmer l’inscription',
                'attr' => [
                    'class' => 'btn btn-eco rounded-pill px-4 py-2'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Inscription::class,
        ]);
    }
}