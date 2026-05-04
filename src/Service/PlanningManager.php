<?php
namespace App\Service;

use App\Entity\Planning;

class PlanningManager
{
    public function validate(Planning $planning): bool
    {
        if (empty($planning->getTitre())) {
            throw new \InvalidArgumentException('Titre obligatoire');
        }

        if ($planning->getDateFin() <= $planning->getDateDebut()) {
            throw new \InvalidArgumentException('Dates invalides');
        }

        if (empty($planning->getStatut())) {
            throw new \InvalidArgumentException('Statut obligatoire');
        }

        return true;
    }
}