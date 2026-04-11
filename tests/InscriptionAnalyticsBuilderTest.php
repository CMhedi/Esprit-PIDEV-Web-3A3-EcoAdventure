<?php

namespace App\Tests;

use App\Dto\PackInsightView;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutInscription;
use App\Service\Inscription\InscriptionAnalyticsBuilder;
use App\Service\Inscription\InscriptionPriorityScorer;
use PHPUnit\Framework\TestCase;

final class InscriptionAnalyticsBuilderTest extends TestCase
{
    public function testBuildReturnsPrioritySummaryAndSegments(): void
    {
        $builder = new InscriptionAnalyticsBuilder(new InscriptionPriorityScorer());

        $pack = (new Pack())
            ->setNom('Pack Smart')
            ->setTypePack('Premium')
            ->setPrixBase('200')
            ->setReduction('20')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 10);

        $user = (new UserApp())
            ->setNom('Doe')
            ->setPrenom('Jane')
            ->setEmail('jane@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Jane Doe')
            ->setNomPack('Pack Smart')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable('-1 day'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        $this->setEntityId($inscription, 'id_inscription', 99);
        $user->getInscriptions()->add($inscription);

        $packInsight = new PackInsightView(
            $pack,
            82.5,
            1,
            ['Tendance'],
            ['une réduction réellement compétitive'],
            ['final_price' => 180.0]
        );

        $analytics = $builder->build([$inscription], [10 => $packInsight]);

        self::assertSame(1, $analytics['summary']['total']);
        self::assertSame(1, $analytics['summary']['pending_count']);
        self::assertSame(1, $analytics['summary']['high_priority_count']);
        self::assertSame(1, $analytics['segments']['Prioritaire']);
        self::assertCount(1, $analytics['priority_views']);
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
