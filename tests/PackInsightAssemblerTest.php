<?php

namespace App\Tests;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Enum\StatutInscription;
use App\Repository\FeedbackEventRepository;
use App\Service\Pack\PackInsightAssembler;
use PHPUnit\Framework\TestCase;

final class PackInsightAssemblerTest extends TestCase
{
    public function testBuildInsightsRanksTheMostAttractivePackFirst(): void
    {
        $feedbackRepository = $this->createMock(FeedbackEventRepository::class);
        $feedbackRepository
            ->method('getPackActionCounts')
            ->willReturn([
                1 => ['view_pack_detail' => 8, 'open_inscription' => 5, 'inscription_confirmed' => 3],
                2 => ['view_pack_detail' => 1, 'open_inscription' => 1, 'inscription_confirmed' => 0],
            ]);

        $assembler = new PackInsightAssembler($feedbackRepository);

        $packA = (new Pack())
            ->setNom('Pack A')
            ->setTypePack('Premium')
            ->setPrixBase('220')
            ->setReduction('40')
            ->setNbActivitesMax(6)
            ->setStatutPack('ACTIF');
        $this->setEntityId($packA, 'id_pack', 1);

        $packB = (new Pack())
            ->setNom('Pack B')
            ->setTypePack('Loisir')
            ->setPrixBase('140')
            ->setReduction('5')
            ->setNbActivitesMax(3)
            ->setStatutPack('INACTIF');
        $this->setEntityId($packB, 'id_pack', 2);

        $recentInscription = (new Inscription())
            ->setNomUser('Alice')
            ->setNomPack('Pack A')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable('-2 days'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE)
            ->setPack($packA);
        $packA->getInscriptions()->add($recentInscription);

        $olderInscription = (new Inscription())
            ->setNomUser('Bob')
            ->setNomPack('Pack B')
            ->setMontantTotal('135')
            ->setDateInscription(new \DateTimeImmutable('-40 days'))
            ->setStatutInscr(StatutInscription::VALIDEE)
            ->setPack($packB);
        $packB->getInscriptions()->add($olderInscription);

        $insights = $assembler->buildInsights([$packA, $packB]);

        self::assertSame(1, $insights[1]->getRank());
        self::assertGreaterThan($insights[2]->getScore(), $insights[1]->getScore());
        self::assertNotEmpty($insights[1]->getBadges());
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
