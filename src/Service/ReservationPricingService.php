<?php

namespace App\Service;

use App\Entity\Evenement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ReservationPricingService
{
    private int $promoThreshold;
    private float $promoDiscount;

    public function __construct(
        #[Autowire('%app.promo_group_threshold%')] int $promoThreshold,
        #[Autowire('%app.promo_group_discount%')] float $promoDiscount
    ) {
        $this->promoThreshold = $promoThreshold;
        $this->promoDiscount = $promoDiscount;
    }

    /**
     * Calcule le prix total pour une réservation
     * Retourne un tableau avec [sousTotal, remise, totalFinal, appliquePromo]
     */
    public function calculatePricing(Evenement $evenement, int $nbBillets): array
    {
        $prixUnitaire = $evenement->getPrix();
        $sousTotal = $prixUnitaire * $nbBillets;
        
        $remise = 0.0;
        $appliquePromo = false;

        if ($nbBillets >= $this->promoThreshold) {
            $remise = $sousTotal * $this->promoDiscount;
            $appliquePromo = true;
        }

        $totalFinal = $sousTotal - $remise;

        return [
            'sousTotal' => $sousTotal,
            'remise' => $remise,
            'totalFinal' => $totalFinal,
            'appliquePromo' => $appliquePromo,
            'tauxRemise' => $this->promoDiscount * 100, // ex: 10 pour 10%
            'threshold' => $this->promoThreshold
        ];
    }
    
    public function getPromoThreshold(): int
    {
        return $this->promoThreshold;
    }

    public function getPromoDiscount(): float
    {
        return $this->promoDiscount;
    }
}
