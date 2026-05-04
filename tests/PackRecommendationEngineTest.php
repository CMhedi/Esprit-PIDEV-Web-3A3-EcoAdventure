<?php

namespace App\Tests;

use App\Dto\PackInsightView;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\StatutInscription;
use App\Service\Pack\PackRecommendationEngine;
use PHPUnit\Framework\TestCase;

final class PackRecommendationEngineTest extends TestCase
{
    public function testAnonymousRecommendationsUseInsightScoreOrder(): void
    {
        $engine = new PackRecommendationEngine();

        $lowScorePack = $this->createPack(1, 'Starter', 'Nature', '90', '0', 3);
        $highScorePack = $this->createPack(2, 'Premium', 'Adventure', '180', '20', 6);

        $recommendations = $engine->recommendForUser(
            [$lowScorePack, $highScorePack],
            [
                1 => $this->createInsight($lowScorePack, 45.0),
                2 => $this->createInsight($highScorePack, 88.0),
            ],
            null,
            1,
        );

        self::assertCount(1, $recommendations);
        self::assertSame($highScorePack, $recommendations[0]->getPack());
    }

    public function testUserRecommendationsFavorMatchingUnknownPacks(): void
    {
        $engine = new PackRecommendationEngine();

        $knownPack = $this->createPack(1, 'Known Adventure', 'Adventure', '110', '10', 5);
        $matchingNewPack = $this->createPack(2, 'Fresh Adventure', 'Adventure', '105', '5', 5);
        $distantPack = $this->createPack(3, 'Relax', 'Wellness', '260', '0', 2);

        $user = new UserApp();
        $user->addInscription(
            (new Inscription())
                ->setDateInscription(new \DateTimeImmutable('-10 days'))
                ->setStatutInscr(StatutInscription::VALIDEE)
                ->setMontantTotal('100.00')
                ->setNomUser('Alice')
                ->setNomPack('Known Adventure')
                ->setPack($knownPack)
        );

        $recommendations = $engine->recommendForUser(
            [$knownPack, $matchingNewPack, $distantPack],
            [
                1 => $this->createInsight($knownPack, 100.0),
                2 => $this->createInsight($matchingNewPack, 60.0),
                3 => $this->createInsight($distantPack, 95.0),
            ],
            $user,
            2,
        );

        self::assertSame($matchingNewPack, $recommendations[0]->getPack());
        self::assertGreaterThan($recommendations[1]->getRecommendationScore(), $recommendations[0]->getRecommendationScore());
    }

    private function createPack(int $id, string $name, string $type, string $price, string $discount, int $capacity): Pack
    {
        $pack = (new Pack())
            ->setNom($name)
            ->setTypePack($type)
            ->setPrixBase($price)
            ->setReduction($discount)
            ->setNbActivitesMax($capacity)
            ->setStatutPack('ACTIF');

        $this->setEntityId($pack, 'id_pack', $id);

        return $pack;
    }

    private function createInsight(Pack $pack, float $score): PackInsightView
    {
        return new PackInsightView($pack, $score, 1, ['Test'], ['Reason'], []);
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
