<?php

namespace App\Service;

use App\Entity\Reclamation;

class ReclamationProcessor
{
    public function calculatePriority(Reclamation $reclamation): void
    {
        $text = strtolower($reclamation->getContenu() . ' ' . $reclamation->getType());
        
        // Kelmet elli ybaynou ennou el sujet urgent
        $urgentKeywords = ['urgent', 'panne', 'danger', 'vol', 'accident', 'remboursement', 'problème grave'];
        $mediumKeywords = ['retard', 'mauvais', 'déçu', 'faute', 'coach'];

        $priority = 'BASSE';

        foreach ($urgentKeywords as $word) {
            if (str_contains($text, $word)) {
                $priority = 'HAUTE';
                break;
            }
        }

        if ($priority === 'BASSE') {
            foreach ($mediumKeywords as $word) {
                if (str_contains($text, $word)) {
                    $priority = 'MOYENNE';
                    break;
                }
            }
        }

        $reclamation->setPriorite($priority);
    }
}