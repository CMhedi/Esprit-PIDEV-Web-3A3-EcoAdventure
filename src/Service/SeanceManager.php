<?php
namespace App\Service;

use App\Entity\Seance;

class SeanceManager
{
    public function validate(Seance $seance): bool
    {
        if (!$seance->getDateSeance()) {
            throw new \InvalidArgumentException('Date obligatoire');
        }

        if ($seance->getHeureFin() <= $seance->getHeureDebut()) {
            throw new \InvalidArgumentException('Heure fin invalide');
        }

        if ($seance->getCapacite() <= 0) {
            throw new \InvalidArgumentException('Capacité invalide');
        }

        return true;
    }
}