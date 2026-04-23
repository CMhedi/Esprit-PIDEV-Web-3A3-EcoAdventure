<?php

namespace App\Tests;

use App\Dto\PackRiskView;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutInscription;
use App\Service\Risk\InscriptionRiskEngine;
use App\Service\Risk\RiskActionResolver;
use App\Service\Risk\RiskInterpreter;
use App\Service\Risk\RiskLevelClassifier;
use PHPUnit\Framework\TestCase;

final class InscriptionRiskEngineTest extends TestCase
{
    public function testEvaluateEscalatesPendingHighValueInscription(): void
    {
        $engine = new InscriptionRiskEngine(
            new RiskLevelClassifier(),
            new RiskInterpreter(),
            new RiskActionResolver(),
        );

        $riskyPack = (new Pack())
            ->setNom('Pack Risky')
            ->setTypePack('Extreme')
            ->setPrixBase('260')
            ->setReduction('0')
            ->setNbActivitesMax(6)
            ->setStatutPack('INACTIF');
        $this->setEntityId($riskyPack, 'id_pack', 10);

        $safePack = (new Pack())
            ->setNom('Pack Safe')
            ->setTypePack('Decouverte')
            ->setPrixBase('120')
            ->setReduction('15')
            ->setNbActivitesMax(4)
            ->setStatutPack('ACTIF');
        $this->setEntityId($safePack, 'id_pack', 20);

        $userA = (new UserApp())
            ->setNom('Doe')
            ->setPrenom('John')
            ->setEmail('john-risk@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $userB = (new UserApp())
            ->setNom('Doe')
            ->setPrenom('Jane')
            ->setEmail('jane-safe@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $riskyInscription = (new Inscription())
            ->setPack($riskyPack)
            ->setUserApp($userA)
            ->setNomUser('John Doe')
            ->setNomPack('Pack Risky')
            ->setMontantTotal('260')
            ->setDateInscription(new \DateTimeImmutable('-9 days'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        $this->setEntityId($riskyInscription, 'id_inscription', 100);
        $userA->getInscriptions()->add($riskyInscription);

        $safeInscription = (new Inscription())
            ->setPack($safePack)
            ->setUserApp($userB)
            ->setNomUser('Jane Doe')
            ->setNomPack('Pack Safe')
            ->setMontantTotal('90')
            ->setDateInscription(new \DateTimeImmutable('-1 day'))
            ->setStatutInscr(StatutInscription::VALIDEE);
        $this->setEntityId($safeInscription, 'id_inscription', 200);
        $userB->getInscriptions()->add($safeInscription);

        $views = $engine->evaluate(
            [$riskyInscription, $safeInscription],
            [
                10 => new PackRiskView($riskyPack, 82.0, 'Critical Risk', 'Fragile', 'Pack critique', ['statut'], ['repositionner'], ['status' => 0.9], 24.0),
                20 => new PackRiskView($safePack, 18.0, 'Low Risk', 'Sain et performant', 'Pack sain', ['aucun signal'], ['mettre en avant'], ['status' => 0.1], 82.0),
            ]
        );

        self::assertGreaterThan($views[200]->getRiskScore(), $views[100]->getRiskScore());
        self::assertContains($views[100]->getRiskLevel(), ['High Risk', 'Critical Risk']);
        self::assertContains($views[200]->getRiskLevel(), ['Low Risk', 'Medium Risk']);
        self::assertNotEmpty($views[100]->getRecommendedActions());
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
