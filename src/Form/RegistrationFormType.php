<?php

namespace App\Form;

use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\Specialite;
use App\Enum\Disponibilite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class)
            ->add('prenom', TextType::class)
            ->add('telephone', \Symfony\Component\Form\Extension\Core\Type\TelType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Votre numéro de téléphone'],
                'label' => 'Téléphone',
                'required' => true, 
            ])
            ->add('email', TextType::class)
            ->add('role', EnumType::class, [
                'class' => RoleUser::class,
                'choices' => [RoleUser::USER_SIMPLE, RoleUser::COACH],
                'choice_label' => fn (RoleUser $choice) => $choice->value,
            ])

            // Fields Coach: Kolhom lezem yabdaw 'required' => false
            ->add('age', IntegerType::class, [
                'required' => false, 
                'attr' => ['min' => 18, 'placeholder' => 'Votre âge']
            ])
            ->add('experience', IntegerType::class, [
                'required' => false, 
                'label' => "Années d'expérience",
                'attr' => ['min' => 0, 'placeholder' => 'Ex: 2']
            ])
            ->add('bio_certifs', TextareaType::class, [
                'required' => false, 
                'label' => 'Bio (Optionnel)',
            ])

            // Salla7na el required houni bech el USER_SIMPLE i-najem i-3addi
            ->add('specialite', EnumType::class, [
                'class' => Specialite::class,
                'choice_label' => fn ($choice) => $choice->value,
                'required' => false, // <--- Kenet TRUE, badeltha FALSE
                'placeholder' => 'Choisir une spécialité',
                'attr' => ['class' => 'form-select']
            ])

            ->add('disponibilite', EnumType::class, [
                'class' => Disponibilite::class,
                'choice_label' => fn ($choice) => $choice->value, 
                'required' => false, // <--- Kenet TRUE, badeltha FALSE
                'placeholder' => 'Choisir votre disponibilité',
                'attr' => ['class' => 'form-select']
            ])
            
            ->add('agreeTerms', CheckboxType::class, ['mapped' => false])
            ->add('motdepasse', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false, // <--- AJOUTE CETTE LIGNE ICI
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Assert\Length([
                        'min' => 8,
                        'minMessage' => 'Votre mot de passe doit avoir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        'message' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control rounded-pill',
                    'placeholder' => 'Min. 8 caractères (A, a, 1...)'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => UserApp::class]);
    }
}