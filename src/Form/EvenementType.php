<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('description')
            ->add('categorie_evt', \Symfony\Component\Form\Extension\Core\Type\EnumType::class, [
                'class' => \App\Enum\CategorieEvenement::class,
                'choice_label' => fn (\App\Enum\CategorieEvenement $choice) => $choice->value,
            ])
            ->add('date_event', null, [
                'widget' => 'single_text'
            ])
            ->add('lieu')
            ->add('nb_places')
            ->add('image_url', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Image (Optionnelle)',
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
