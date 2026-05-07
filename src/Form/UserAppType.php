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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;

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
            ->add('age', null, [
                'attr' => [
                    'min' => 18,
                    'max' => 40
                ]
            ])
            ->add('role', EnumType::class, [
                'class' => RoleUser::class,
                'disabled' => !$isAdmin, // User cannot change their own role
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF, WEBP)',
                    ])
                ],
                'attr' => [
                    'accept' => 'image/jpeg, image/png, image/gif, image/webp'
                ]
            ]);

        // Only show coach fields if the user is a coach or it's an admin editing
        if ($isCoach || $isAdmin) {
            $builder
                ->add('specialite', EnumType::class, ['class' => Specialite::class])
                ->add('disponibilite', EnumType::class, ['class' => Disponibilite::class])
                ->add('experience', TextType::class, [
                    'label' => 'Expérience (en années)',
                    'required' => false,
                    'attr' => ['placeholder' => 'Ex: 5']
                ])
                ->add('bioCertifs');
        }

        $isNew = !($options['data'] && $options['data']->getId_user());

        $passwordConstraints = [
            new Length([
                'min' => 8,
                'minMessage' => 'Votre mot de passe doit avoir au moins {{ limit }} caractères',
                'max' => 4096,
            ]),
            new Assert\Regex([
                'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                'message' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.'
            ])
        ];

        if ($isNew) {
            $passwordConstraints[] = new Assert\NotBlank([
                'message' => 'Le mot de passe est obligatoire pour un nouvel utilisateur.',
            ]);
        }

        $builder->add('plainPassword', PasswordType::class, [
            'mapped' => false,
            'required' => $isNew,
            'label' => $isNew ? 'Mot de passe' : 'Nouveau mot de passe (laisser vide pour ne pas changer)',
            'constraints' => $passwordConstraints,
            'attr' => [
                'class' => 'form-control',
                'placeholder' => $isNew ? 'Entrez un mot de passe...' : 'Entrez un nouveau mot de passe...'
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
