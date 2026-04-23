<?php

namespace App\Form;

use App\Dto\PackInscriptionRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InscriptionPackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirmPackSelection', CheckboxType::class, [
                'label' => 'Je confirme mes informations et la selection de ce pack.',
                'required' => false,
            ])
            ->add('acceptPaymentStep', CheckboxType::class, [
                'label' => 'Je comprends que le paiement sera realise sur une page separee et securisee.',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => "Continuer vers le paiement",
                'attr' => [
                    'class' => 'btn btn-eco rounded-pill px-4 py-3',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PackInscriptionRequest::class,
        ]);
    }
}
