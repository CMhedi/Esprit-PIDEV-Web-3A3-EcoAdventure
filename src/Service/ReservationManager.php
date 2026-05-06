<?php
namespace App\Service;

use App\Entity\ReservationSeance;

class ReservationManager
{
   public function validate(ReservationSeance $reservation, int $currentCount, int $capacity): bool
{
    if (!$reservation->getDate_reservation()) {
        throw new \InvalidArgumentException('Date obligatoire');
    }

    if ($currentCount >= $capacity) {
        throw new \InvalidArgumentException('Séance complète');
    }

    if (!$reservation->getStatut()) {
        throw new \InvalidArgumentException('Statut obligatoire');
    }

    return true;
}
}