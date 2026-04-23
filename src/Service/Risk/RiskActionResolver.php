<?php

namespace App\Service\Risk;

use App\Dto\PackRiskView;

final class RiskActionResolver
{
    /**
     * @param array<string, float> $components
     * @param array<string, float|int|string> $metrics
     * @return string[]
     */
    public function resolvePackActions(string $riskLevel, string $profile, array $components, array $metrics): array
    {
        $actions = [];

        if ($riskLevel === 'Low Risk') {
            $actions[] = 'mettre en avant ce pack';
            $actions[] = 'surveiller passivement sa stabilite';
        }

        if ($profile === 'Sous-exploite') {
            $actions[] = 'renforcer la mise en avant commerciale';
            $actions[] = 'fluidifier le parcours d inscription';
        }

        if ($profile === 'A repositionner') {
            $actions[] = 'revoir le prix final ou la reduction';
            $actions[] = 'clarifier la promesse de valeur du pack';
        }

        if (($components['coverage'] ?? 0.0) >= 0.55) {
            $actions[] = 'aligner les activites liees avec la promesse du pack';
        }

        if (($components['status'] ?? 0.0) >= 0.6) {
            $actions[] = 'verifier le statut et la disponibilite admin';
        }

        if (($components['conversion'] ?? 0.0) >= 0.58) {
            $actions[] = 'analyser le tunnel vue vers inscription';
        }

        if (($components['demand'] ?? 0.0) >= 0.6) {
            $actions[] = 'comparer le pack aux offres du meme type';
        }

        if (($components['pending'] ?? 0.0) >= 0.55) {
            $actions[] = 'traiter les inscriptions en attente liees a ce pack';
        }

        if ($riskLevel === 'Critical Risk') {
            $actions[] = 'declencher une revue admin immediate';
        }

        if ((float) ($metrics['inscriptions_total'] ?? 0.0) === 0.0 && $riskLevel !== 'Low Risk') {
            $actions[] = 'tester une campagne de mise en avant ou une alternative';
        }

        return array_slice(array_values(array_unique($actions)), 0, 4);
    }

    /**
     * @param array<string, float> $components
     * @return string[]
     */
    public function resolveInscriptionActions(string $riskLevel, array $components, ?PackRiskView $packRiskView): array
    {
        $actions = [];

        if ($riskLevel === 'Low Risk') {
            $actions[] = 'laisser suivre le cycle standard';
        }

        if (($components['workflow'] ?? 0.0) >= 0.6) {
            $actions[] = 'prioriser le traitement manuel du dossier';
        }

        if (($components['urgency'] ?? 0.0) >= 0.55) {
            $actions[] = 'relancer ou confirmer rapidement cette inscription';
        }

        if (($components['pack_propagation'] ?? 0.0) >= 0.6) {
            $actions[] = 'verifier le pack lie avant validation finale';
        }

        if (($components['anomaly'] ?? 0.0) >= 0.55) {
            $actions[] = 'controler la coherence montant / historique utilisateur';
        }

        if ($packRiskView && $packRiskView->getRiskScore() >= 75.0) {
            $actions[] = 'proposer une alternative pack plus saine';
        }

        if ($riskLevel === 'Critical Risk') {
            $actions[] = 'signaler ce dossier a l administrateur';
        }

        return array_slice(array_values(array_unique($actions)), 0, 4);
    }
}
