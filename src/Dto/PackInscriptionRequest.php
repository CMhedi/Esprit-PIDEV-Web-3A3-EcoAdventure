<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PackInscriptionRequest
{
    #[Assert\IsTrue(message: 'Veuillez confirmer les informations de votre inscription.')]
    public bool $confirmPackSelection = false;

    #[Assert\IsTrue(message: 'Veuillez accepter de passer au paiement sur l etape suivante.')]
    public bool $acceptPaymentStep = false;
}
