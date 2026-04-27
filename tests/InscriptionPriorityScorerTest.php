<?php

namespace App\Tests;

use App\Dto\PackInsightView;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutInscription;
use App\Service\Inscription\InscriptionPriorityScorer;
use PHPUnit\Framework\TestCase;

final class InscriptionPriorityScorerTest extends TestCase
{
    private InscriptionPriorityScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new InscriptionPriorityScorer();
    }

    public function testScoreReturnsHighPriorityForPendingHighValueInscription(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Premium')
            ->setTypePack('Aventure')
            ->setPrixBase('300')
            ->setReduction('10')
            ->setNbActivitesMax(8)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 1);

        $user = (new UserApp())
            ->setNom('Martin')
            ->setPrenom('Alice')
            ->setEmail('alice@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        // Add several previous inscriptions to increase user value
        for ($i = 0; $i < 5; ++$i) {
            $oldInscription = (new Inscription())
                ->setPack($pack)
                ->setUserApp($user)
                ->setNomUser('Alice Martin')
                ->setNomPack('Pack Premium')
                ->setMontantTotal('250')
                ->setDateInscription(new \DateTimeImmutable('-'.(30 + $i).' days'))
                ->setStatutInscr(StatutInscription::VALIDEE);
            $user->getInscriptions()->add($oldInscription);
        }

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Alice Martin')
            ->setNomPack('Pack Premium')
            ->setMontantTotal('270')
            ->setDateInscription(new \DateTimeImmutable('-2 days'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        $user->getInscriptions()->add($inscription);

        $packInsight = new PackInsightView(
            $pack,
            85.0,
            1,
            ['Tendance'],
            ['forte demande'],
            ['final_price' => 270.0]
        );

        $priorityView = $this->scorer->score(
            $inscription,
            [1 => $packInsight],
            1000.0
        );

        self::assertGreaterThanOrEqual(70, $priorityView->getPriorityScore());
        self::assertTrue($priorityView->isHighPriority());
        self::assertSame('Prioritaire', $priorityView->getLane());
    }

    public function testScoreReturnsConfirmLaneForConfirmedMediumValueInscription(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Standard')
            ->setTypePack('Decouverte')
            ->setPrixBase('150')
            ->setReduction('20')
            ->setNbActivitesMax(4)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 2);

        $user = (new UserApp())
            ->setNom('Dupont')
            ->setPrenom('Jean')
            ->setEmail('jean@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Jean Dupont')
            ->setNomPack('Pack Standard')
            ->setMontantTotal('120')
            ->setDateInscription(new \DateTimeImmutable('-5 days'))
            ->setStatutInscr(StatutInscription::CONFIRMEE);
        $user->getInscriptions()->add($inscription);

        $packInsight = new PackInsightView(
            $pack,
            70.0,
            2,
            ['Populaire'],
            ['bon rapport qualité-prix'],
            ['final_price' => 120.0]
        );

        $priorityView = $this->scorer->score(
            $inscription,
            [2 => $packInsight],
            1000.0
        );

        self::assertGreaterThanOrEqual(55, $priorityView->getPriorityScore());
        self::assertLessThan(75, $priorityView->getPriorityScore());
        self::assertSame('À confirmer', $priorityView->getLane());
    }

    public function testScoreReturnsStableLaneForValidatedOldInscription(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Budget')
            ->setTypePack('Decouverte')
            ->setPrixBase('80')
            ->setReduction('5')
            ->setNbActivitesMax(2)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 3);

        $user = (new UserApp())
            ->setNom('Bernard')
            ->setPrenom('Marc')
            ->setEmail('marc@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Marc Bernard')
            ->setNomPack('Pack Budget')
            ->setMontantTotal('76')
            ->setDateInscription(new \DateTimeImmutable('-25 days'))
            ->setStatutInscr(StatutInscription::VALIDEE);
        $user->getInscriptions()->add($inscription);

        $packInsight = new PackInsightView(
            $pack,
            60.0,
            3,
            ['Économique'],
            ['prix attractif'],
            ['final_price' => 76.0]
        );

        $priorityView = $this->scorer->score(
            $inscription,
            [3 => $packInsight],
            1000.0
        );

        self::assertGreaterThanOrEqual(35, $priorityView->getPriorityScore());
        self::assertLessThan(55, $priorityView->getPriorityScore());
        self::assertSame('Stable', $priorityView->getLane());
    }

    public function testScoreReturnsFaibleLaneForCancelledInscription(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Cancelled')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('0')
            ->setNbActivitesMax(5)
            ->setStatutPack('INACTIF');
        $this->setEntityId($pack, 'id_pack', 4);

        $user = (new UserApp())
            ->setNom('Lefevre')
            ->setPrenom('Sophie')
            ->setEmail('sophie@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Sophie Lefevre')
            ->setNomPack('Pack Cancelled')
            ->setMontantTotal('200')
            ->setDateInscription(new \DateTimeImmutable('-35 days'))
            ->setStatutInscr(StatutInscription::ANNULEE);
        $user->getInscriptions()->add($inscription);

        $packInsight = new PackInsightView(
            $pack,
            40.0,
            4,
            ['À relancer'],
            ['peu de demande'],
            ['final_price' => 200.0]
        );

        $priorityView = $this->scorer->score(
            $inscription,
            [4 => $packInsight],
            1000.0
        );

        self::assertLessThan(35, $priorityView->getPriorityScore());
        self::assertFalse($priorityView->isHighPriority());
        self::assertSame('Faible', $priorityView->getLane());
    }

    public function testScoreConsidersUserHistoryForUserValue(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Test')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 5);

        $user = (new UserApp())
            ->setNom('Frequent')
            ->setPrenom('Client')
            ->setEmail('client@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        // Add 5 previous inscriptions to simulate loyal user
        for ($i = 0; $i < 5; ++$i) {
            $oldInscription = (new Inscription())
                ->setPack($pack)
                ->setUserApp($user)
                ->setNomUser('Client Frequent')
                ->setNomPack('Pack Test')
                ->setMontantTotal('180')
                ->setDateInscription(new \DateTimeImmutable("-".(30 + $i)." days"))
                ->setStatutInscr(StatutInscription::VALIDEE);
            $user->getInscriptions()->add($oldInscription);
        }

        $currentInscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Client Frequent')
            ->setNomPack('Pack Test')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable('-1 day'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        $user->getInscriptions()->add($currentInscription);

        $packInsight = new PackInsightView(
            $pack,
            75.0,
            1,
            ['Fidélité'],
            ['client loyal'],
            ['final_price' => 180.0]
        );

        $priorityView = $this->scorer->score(
            $currentInscription,
            [5 => $packInsight],
            1000.0
        );

        self::assertGreaterThanOrEqual(50, $priorityView->getPriorityScore());
        self::assertSame(6, $priorityView->getUserHistoryCount());
    }

    public function testScoreHandlesNullPackInsight(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Unknown')
            ->setTypePack('Aventure')
            ->setPrixBase('150')
            ->setReduction('15')
            ->setNbActivitesMax(4)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 999);

        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Test User')
            ->setNomPack('Pack Unknown')
            ->setMontantTotal('127')
            ->setDateInscription(new \DateTimeImmutable('-3 days'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        $user->getInscriptions()->add($inscription);

        // Empty insights array - pack not in insights
        $priorityView = $this->scorer->score($inscription, [], 1000.0);

        self::assertNotNull($priorityView);
        self::assertLessThan(100, $priorityView->getPriorityScore());
        self::assertGreaterThan(0, $priorityView->getPriorityScore());
    }

    public function testScoreNormalizesAmountToMaxAmount(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Expensive')
            ->setTypePack('Premium')
            ->setPrixBase('5000')
            ->setReduction('50')
            ->setNbActivitesMax(10)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 100);

        $user = (new UserApp())
            ->setNom('Rich')
            ->setPrenom('Customer')
            ->setEmail('rich@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Rich Customer')
            ->setNomPack('Pack Expensive')
            ->setMontantTotal('2500')
            ->setDateInscription(new \DateTimeImmutable('-1 day'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        $user->getInscriptions()->add($inscription);

        $packInsight = new PackInsightView(
            $pack,
            90.0,
            1,
            ['Premium'],
            ['luxury service'],
            ['final_price' => 2500.0]
        );

        // maxAmount is low, so amount should be normalized to 1.0
        $priorityView = $this->scorer->score(
            $inscription,
            [100 => $packInsight],
            100.0  // Very low max
        );

        self::assertGreaterThanOrEqual(50, $priorityView->getPriorityScore());
    }

    public function testScoreExplanationMatchesLane(): void
    {
        $pack = (new Pack())
            ->setNom('Pack Test')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack, 'id_pack', 1);

        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Test User')
            ->setNomPack('Pack Test')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable('-1 day'))
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        $user->getInscriptions()->add($inscription);

        $packInsight = new PackInsightView(
            $pack,
            85.0,
            1,
            ['Tendance'],
            ['good value'],
            ['final_price' => 180.0]
        );

        $priorityView = $this->scorer->score(
            $inscription,
            [1 => $packInsight],
            1000.0
        );

        self::assertNotEmpty($priorityView->getExplanation());
        self::assertStringContainsString(
            match ($priorityView->getLane()) {
                'Prioritaire' => 'traitement',
                'À confirmer' => 'suivre',
                'Stable' => 'saine',
                default => 'faible',
            },
            strtolower($priorityView->getExplanation())
        );
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
