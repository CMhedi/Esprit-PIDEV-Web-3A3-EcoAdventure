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

class UserAppType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('prenom')
            ->add('email')
            ->add('telephone')
            ->add('age')
            /* Ista3mel EnumType bech tna7i error "Object could not be converted to string" */
            ->add('role', EnumType::class, ['class' => RoleUser::class])
            ->add('specialite', EnumType::class, ['class' => Specialite::class])
            ->add('disponibilite', EnumType::class, ['class' => Disponibilite::class])
            ->add('bio_certifs')
            ->add('plainPassword', TextType::class, [
                'mapped' => false, // Symfony ma i-lawajch 3liha f'el Entity
                'required' => false,
                'label' => 'Mot de passe',
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
        ]);
    }
}