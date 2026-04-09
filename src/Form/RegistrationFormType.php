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
            // Champs Coach

            ->add('age', IntegerType::class, [
                'required' => false, // Géré par le Callback pour le Coach
                'attr' => ['min' => 18, 'placeholder' => 'Votre âge']
            ])
            ->add('experience', IntegerType::class, [
                'required' => false, // Géré par le Callback
                'label' => "Années d'expérience",
                'attr' => ['min' => 0, 'placeholder' => 'Ex: 2']
            ])
            ->add('bio_certifs', TextareaType::class, [
                'required' => false, // Désormais optionnel
                'label' => 'Bio (Optionnel)',
            ])
            ->add('specialite', EnumType::class, [
                'class' => Specialite::class,
                'choice_label' => fn ($choice) => $choice->value, // walla esm el label elli t7eb 3lih
                'required' => true,
                'placeholder' => 'Choisir une spécialité',
                'attr' => ['class' => 'form-select']
            ])

            ->add('disponibilite', EnumType::class, [
                'class' => Disponibilite::class,
                'choice_label' => fn ($choice) => $choice->value, 
                'required' => true,
                'placeholder' => 'Choisir votre disponibilité',
                'attr' => ['class' => 'form-select']
            ])
            
            ->add('agreeTerms', CheckboxType::class, ['mapped' => false])
            ->add('motdepasse', PasswordType::class, [
                'mapped' => false,
                'constraints' => [new NotBlank(), new Length(['min' => 6])]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => UserApp::class]);
    }
}