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
     *
     * @return array{sousTotal: float, remise: float, totalFinal: float, appliquePromo: bool, tauxRemise: float, threshold: int}
     */
    public function calculatePricing(Evenement $evenement, int $nbBillets): array
    {
        $prixUnitaire = $evenement->getPrix();
        $sousTotal = $prixUnitaire * $nbBillets;

        $remise = 0.0;
        $appliquePromo = false;
        $currentDiscount = 0.0;

        if ($nbBillets >= $this->promoThreshold) {
            $appliquePromo = true;
            $currentDiscount = $this->promoDiscount; // Base 10%

            if ($nbBillets <= 50) {
                // 📈 Phase 1 : +1% tous les 5 billets supplémentaires (jusqu'à 50)
                $extra = $nbBillets - $this->promoThreshold;
                $currentDiscount += floor($extra / 5) * 0.01;
            } else {
                // 📈 Phase 2 : On atteint 18% à 50 billets, puis +1% tous les 10 billets
                $currentDiscount = 0.18;
                $extraAfter50 = $nbBillets - 50;
                $currentDiscount += floor($extraAfter50 / 10) * 0.01;
            }

            $remise = $sousTotal * $currentDiscount;
        }

        $totalFinal = $sousTotal - $remise;

        return [
            'sousTotal' => $sousTotal,
            'remise' => $remise,
            'totalFinal' => $totalFinal,
            'appliquePromo' => $appliquePromo,
            'tauxRemise' => round($currentDiscount * 100, 2),
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
