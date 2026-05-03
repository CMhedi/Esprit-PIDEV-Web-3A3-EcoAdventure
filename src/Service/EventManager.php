<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Enum\CategorieEvenement;

class EventManager
{
    /**
     * Valide les règles métier complexes d'un événement avant de le sauvegarder.
     * 
     * @param Evenement $evenement
     * @throws \LogicException
     */
    public function validateEventRules(Evenement $evenement): void
    {
        // Règle 1 : La limite de la liste d'attente ne doit pas dépasser la capacité totale de l'événement
        if ($evenement->getLimiteAttente() > $evenement->getNbPlaces()) {
            throw new \LogicException("La limite de la liste d'attente ne peut pas dépasser le nombre total de places.");
        }

        // Règle 2 : Aucun événement ne peut être gratuit
        if ($evenement->getPrix() <= 0) {
            throw new \LogicException("Un événement ne peut pas être gratuit (le prix doit être supérieur à 0).");
        }
    }
}
