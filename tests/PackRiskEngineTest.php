<?php

namespace App\Tests;

use App\Dto\PackInsightView;
use App\Entity\Pack;
use App\Service\Risk\PackRiskEngine;
use App\Service\Risk\RiskActionResolver;
use App\Service\Risk\RiskInterpreter;
use App\Service\Risk\RiskLevelClassifier;
use PHPUnit\Framework\TestCase;

final class PackRiskEngineTest extends TestCase
{
    public function testEvaluateFlagsTheFragilePackAboveTheHealthyPack(): void
    {
        $engine = new PackRiskEngine(
            new RiskLevelClassifier(),
            new RiskInterpreter(),
            new RiskActionResolver(),
        );

        $healthyPack = (new Pack())
            ->setNom('Pack Healthy')
            ->setTypePack('Aventure')
            ->setPrixBase('180')
            ->setReduction('25')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($healthyPack, 'id_pack', 1);

        $fragilePack = (new Pack())
            ->setNom('Pack Fragile')
            ->setTypePack('Aventure')
            ->setPrixBase('260')
            ->setReduction('0')
            ->setNbActivitesMax(6)
            ->setStatutPack('INACTIF');
        $this->setEntityId($fragilePack, 'id_pack', 2);

        $views = $engine->evaluate([
            1 => new PackInsightView(
                $healthyPack,
                84.0,
                1,
                ['Tendance'],
                ['forte demande'],
                [
                    'status_value' => 1.0,
                    'inscriptions_total' => 5,
                    'inscriptions_30d' => 3,
                    'opens_30d' => 5,
                    'confirmed_30d' => 4,
                    'views_30d' => 9,
                    'final_price' => 155.0,
                    'linked_activities' => 5,
                    'coverage_ratio' => 1.0,
                    'reduction_ratio' => 0.14,
                    'pending_count' => 0,
                    'type_label' => 'Aventure',
                ]
            ),
            2 => new PackInsightView(
                $fragilePack,
                24.0,
                2,
                [],
                ['pack a consolider'],
                [
                    'status_value' => 0.35,
                    'inscriptions_total' => 0,
                    'inscriptions_30d' => 0,
                    'opens_30d' => 4,
                    'confirmed_30d' => 0,
                    'views_30d' => 7,
                    'final_price' => 260.0,
                    'linked_activities' => 1,
                    'coverage_ratio' => 0.17,
                    'reduction_ratio' => 0.0,
                    'pending_count' => 0,
                    'type_label' => 'Aventure',
                ]
            ),
        ]);

        self::assertGreaterThan($views[1]->getRiskScore(), $views[2]->getRiskScore());
        self::assertSame('Low Risk', $views[1]->getRiskLevel());
        self::assertContains($views[2]->getRiskLevel(), ['High Risk', 'Critical Risk']);
        self::assertNotEmpty($views[2]->getRecommendedActions());
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
