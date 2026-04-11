<?php
namespace App\Form;

use App\Entity\Planning;
use App\Enum\StatutPlanning;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre'
            ])
            ->add('date_debut', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début'
            ])
            ->add('date_fin', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin'
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Actif' => StatutPlanning::ACTIF,
                    'Brouillon' => StatutPlanning::BROUILLON,
                    'Archivé' => StatutPlanning::ARCHIVE,
                ],
                'label' => 'Statut'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Planning::class,
        ]);
    }
}