<?php

namespace App\Service;

use App\Entity\ReservationActivite;

final class ReservationActiviteManager
{
    /**
     * Validates a reservation for activities.
     *
     * @throws \InvalidArgumentException when validation fails
     */
    public function validate(ReservationActivite $reservation): bool
    {
        // Check if status is set
        if ($reservation->getStatutRes() === null) {
            throw new \InvalidArgumentException('Le statut de reservation est obligatoire.');
        }

        // Check if date is today or in the future
        $today = new \DateTimeImmutable('today');
        if ($reservation->getDateRes() < $today) {
            throw new \InvalidArgumentException('La date de reservation doit etre aujourd hui ou dans le futur.');
        }

        // Check if headcount is between 1 and 100
        $nbPersonnes = $reservation->getNbPersonnes();
        if ($nbPersonnes === null || $nbPersonnes < 1 || $nbPersonnes > 100) {
            throw new \InvalidArgumentException('Le nombre de personnes doit etre entre 1 et 100.');
        }

        return true;
    }
}
