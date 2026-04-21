<?php
// src/Form/UserAppType.php
namespace App\Form;

use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\Specialite;
use App\Enum\Disponibilite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType; // Muhim barcha lel Enums
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints as Assert;

class UserAppType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $options['is_admin'] ?? false;
        $isCoach = $options['is_coach'] ?? false;

        $builder
            ->add('nom')
            ->add('prenom')
            ->add('email', TextType::class, [
                'disabled' => ($options['data'] && $options['data']->getId_user() !== null),
                'attr' => [
                    'readonly' => ($options['data'] && $options['data']->getId_user() !== null),
                    'placeholder' => 'exemple@mail.com'
                ]
            ])
            ->add('telephone')
            ->add('age')
            ->add('role', EnumType::class, [
                'class' => RoleUser::class,
                'disabled' => !$isAdmin, // User cannot change their own role
            ]);

        // Only show coach fields if the user is a coach or it's an admin editing
        if ($isCoach || $isAdmin) {
            $builder
                ->add('specialite', EnumType::class, ['class' => Specialite::class])
                ->add('disponibilite', EnumType::class, ['class' => Disponibilite::class])
                ->add('bioCertifs');
        }

        $builder->add('plainPassword', PasswordType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'Mot de passe',
            'constraints' => [
                new Length([
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
                'class' => 'form-control',
                'placeholder' => 'Entrez un nouveau mot de passe...'
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserApp::class,
            'is_admin' => false,
            'is_coach' => false,
        ]);
    }
}
