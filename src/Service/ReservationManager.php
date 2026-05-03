<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\ReservationEvenement;

class ReservationManager
{
    /**
     * Valide les règles métier de base avant de continuer le processus de réservation.
     * 
     * @param Evenement $evenement
     * @param int $nbBilletsDemandes
     * @throws \LogicException
     */
    public function validateReservationDemande(Evenement $evenement, int $nbBilletsDemandes): void
    {
        // Règle 1 : Vérifier que l'événement est dans le futur
        $now = new \DateTime();
        if ($evenement->getDateEvent() && $evenement->getDateEvent() < $now) {
            throw new \LogicException("Impossible de réserver pour un événement déjà passé.");
        }

        // Règle 2 : Empêcher de réserver plus que la capacité (sauf si = 0 pour liste d'attente)
        $placesRestantes = $evenement->getPlacesRestantes();
        if ($placesRestantes > 0 && $nbBilletsDemandes > $placesRestantes) {
            throw new \LogicException(sprintf("Il ne reste que %d place(s).", $placesRestantes));
        }
    }
}
