<?php

namespace App\Form;

use App\Dto\StripeCheckoutData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class StripeCheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'Titulaire',
                'disabled' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('customerEmail', EmailType::class, [
                'label' => 'Email de facturation',
                'disabled' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('acceptSecurePayment', CheckboxType::class, [
                'label' => 'Je confirme vouloir etre redirige vers Stripe Checkout pour finaliser le paiement securise.',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Payer avec Stripe',
                'attr' => [
                    'class' => 'btn btn-eco rounded-pill px-4 py-3 w-100',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StripeCheckoutData::class,
        ]);
    }
}
