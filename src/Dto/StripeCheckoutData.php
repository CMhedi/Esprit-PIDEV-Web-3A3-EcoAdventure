<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class StripeCheckoutData
{
    #[Assert\NotBlank(message: 'Le nom du client est obligatoire.')]
    #[Assert\Length(max: 120)]
    public string $customerName = '';

    #[Assert\NotBlank(message: 'L email du client est obligatoire.')]
    #[Assert\Email(message: 'Veuillez saisir un email valide.')]
    #[Assert\Length(max: 180)]
    public string $customerEmail = '';

    #[Assert\IsTrue(message: 'Veuillez confirmer le paiement securise Stripe.')]
    public bool $acceptSecurePayment = false;
}
