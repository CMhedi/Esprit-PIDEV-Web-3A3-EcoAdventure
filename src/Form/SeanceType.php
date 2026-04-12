<?php

namespace App\Form;

use App\Entity\Seance;
use App\Entity\UserApp;
use App\Enum\StatutSeance;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Enum\RoleUser;
use Doctrine\ORM\EntityRepository;
class SeanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class)

            ->add('dateSeance', DateType::class, [
                'widget' => 'single_text'
            ])

            ->add('heureDebut', TimeType::class, [
                'widget' => 'single_text'
            ])

            ->add('heureFin', TimeType::class, [
                'widget' => 'single_text'
            ])

            ->add('capacite', IntegerType::class)

            ->add('statutSeance', ChoiceType::class, [
                'choices' => [
                    'Planifiée' => StatutSeance::PLANIFIEE,
                    'Terminée' => StatutSeance::TERMINEE,
                    'Annulée' => StatutSeance::ANNULEE,
                ]
            ])

           ->add('coach', EntityType::class, [
    'class' => UserApp::class,
    'choice_label' => function($user) {
        return $user->getNom() . ' ' . $user->getPrenom();
    },
    'placeholder' => 'Choisir un coach',
    'query_builder' => function (EntityRepository $er) {
        return $er->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', RoleUser::COACH);
    },
])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Seance::class,
        ]);
    }
}