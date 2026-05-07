<?php

namespace App\Service\Inscription;

use App\Dto\InscriptionPriorityView;
use App\Dto\PackInsightView;
use App\Entity\Inscription;

final class InscriptionPriorityScorer
{
    /**
     * @param array<int, PackInsightView> $packInsights
     */
    public function score(Inscription $inscription, array $packInsights, float $maxAmount = 1.0): InscriptionPriorityView
    {
        $statusValue = match ($inscription->getStatutInscr()?->value) {
            'EN_ATTENTE' => 1.0,
            'CONFIRMEE' => 0.7,
            'VALIDEE' => 0.58,
            'ANNULEE' => 0.12,
            default => 0.4,
        };

        $amount = (float) ($inscription->getMontantTotal() ?? 0);
        $amountScore = min($amount / max($maxAmount, 1.0), 1.0);
        $daysOld = $inscription->getDateInscription()
            ? (int) (new \DateTimeImmutable())->diff(\DateTimeImmutable::createFromInterface($inscription->getDateInscription()))->format('%a')
            : 30;
        $recencyScore = max(0.0, 1 - ($daysOld / 30));
        $historyCount = $inscription->getUserApp()?->getInscriptions()->count() ?? 0;
        $userValue = min($historyCount / 5, 1.0);
        $packScore = 0.0;

        if ($inscription->getPack() && $inscription->getPack()->getIdPack() && isset($packInsights[$inscription->getPack()->getIdPack()])) {
            $packScore = $packInsights[$inscription->getPack()->getIdPack()]->getScore();
        }

        $priorityScore = round(100 * (
            (0.4 * $statusValue) +
            (0.2 * $amountScore) +
            (0.18 * $recencyScore) +
            (0.12 * ($packScore / 100)) +
            (0.1 * $userValue)
        ), 1);

        $lane = match (true) {
            $priorityScore >= 75 => 'Prioritaire',
            $priorityScore >= 55 => 'À confirmer',
            $priorityScore >= 35 => 'Stable',
            default => 'Faible',
        };

        $explanation = match ($lane) {
            'Prioritaire' => 'Montant, statut et potentiel pack justifient un traitement rapide.',
            'À confirmer' => 'Dossier à suivre de près, surtout si le pack reste stratégique.',
            'Stable' => 'Inscription saine sans urgence immédiate.',
            default => 'Impact métier faible ou dossier déjà neutralisé.',
        };

        return new InscriptionPriorityView(
            $inscription,
            $priorityScore,
            $lane,
            $explanation,
            $packScore,
            $historyCount,
        );
    }
}
