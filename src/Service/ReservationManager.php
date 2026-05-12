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

    public function validateReservationDemande(\App\Entity\Evenement $evenement, int $nbBillets): void
    {
        if ($nbBillets <= 0) {
            throw new \LogicException("Le nombre de billets doit être supérieur à 0.");
        }

        $now = new \DateTime();
        if ($evenement->getDateEvent() < $now) {
            throw new \LogicException("Cet événement est déjà passé.");
        }
    }
}