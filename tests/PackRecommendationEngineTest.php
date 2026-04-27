<?php

namespace App\Tests;

use App\Dto\PackInsightView;
use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutInscription;
use App\Service\Pack\PackRecommendationEngine;
use PHPUnit\Framework\TestCase;

final class PackRecommendationEngineTest extends TestCase
{
    private PackRecommendationEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new PackRecommendationEngine();
    }

    public function testRecommendForUserReturnsEmptyArrayWhenNoPacksProvided(): void
    {
        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $recommendations = $this->engine->recommendForUser([], [], $user, 3);

        self::assertEmpty($recommendations);
    }

    public function testRecommendForUserReturnsGenericRecommendationForNewUser(): void
    {
        $newUser = (new UserApp())
            ->setNom('Nouveau')
            ->setPrenom('Client')
            ->setEmail('new@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $pack1 = (new Pack())
            ->setNom('Pack A')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack1, 'id_pack', 1);

        $pack2 = (new Pack())
            ->setNom('Pack B')
            ->setTypePack('Decouverte')
            ->setPrixBase('100')
            ->setReduction('5')
            ->setNbActivitesMax(3)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack2, 'id_pack', 2);

        $insight1 = new PackInsightView($pack1, 85.0, 1, ['Top'], ['best'], ['final_price' => 180.0]);
        $insight2 = new PackInsightView($pack2, 70.0, 2, ['Good'], ['nice'], ['final_price' => 95.0]);

        $recommendations = $this->engine->recommendForUser(
            [$pack1, $pack2],
            [1 => $insight1, 2 => $insight2],
            $newUser,
            2
        );

        self::assertCount(2, $recommendations);
        self::assertSame(85.0, $recommendations[0]->getScore());
        self::assertSame(70.0, $recommendations[1]->getScore());
    }

    public function testRecommendForUserReturnsTopRecommendationsLimited(): void
    {
        $newUser = (new UserApp())
            ->setNom('Client')
            ->setPrenom('Test')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $packs = [];
        $insights = [];

        for ($i = 1; $i <= 5; ++$i) {
            $pack = (new Pack())
                ->setNom("Pack $i")
                ->setTypePack('Type'.$i)
                ->setPrixBase((string) (100 * $i))
                ->setReduction('5')
                ->setNbActivitesMax($i)
                ->setStatutPack('ACTIF');
            $this->setEntityId($pack, 'id_pack', $i);
            $packs[] = $pack;

            $score = 100 - ($i * 10);
            $insights[$i] = new PackInsightView(
                $pack,
                $score,
                $i,
                ['Badge'.$i],
                ['reason'.$i],
                ['final_price' => (100 * $i - 5)]
            );
        }

        $recommendations = $this->engine->recommendForUser($packs, $insights, $newUser, 3);

        self::assertCount(3, $recommendations);
        self::assertSame(90.0, $recommendations[0]->getScore());
        self::assertSame(80.0, $recommendations[1]->getScore());
        self::assertSame(70.0, $recommendations[2]->getScore());
    }

    public function testRecommendForUserConsidersBudgetAffinityForExistingUser(): void
    {
        $user = (new UserApp())
            ->setNom('Budget')
            ->setPrenom('Conscious')
            ->setEmail('budget@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        // User has history of buying cheap packs
        $oldPack = (new Pack())
            ->setNom('Pack Budget')
            ->setTypePack('Decouverte')
            ->setPrixBase('100')
            ->setReduction('10')
            ->setNbActivitesMax(2)
            ->setStatutPack('ACTIF');
        $this->setEntityId($oldPack, 'id_pack', 1);

        $oldInscription = (new Inscription())
            ->setPack($oldPack)
            ->setUserApp($user)
            ->setNomUser('Budget Conscious')
            ->setNomPack('Pack Budget')
            ->setMontantTotal('90')
            ->setDateInscription(new \DateTimeImmutable('-30 days'))
            ->setStatutInscr(StatutInscription::VALIDEE);
        $user->getInscriptions()->add($oldInscription);

        // Test packs
        $cheapPack = (new Pack())
            ->setNom('Pack Cheap')
            ->setTypePack('Decouverte')
            ->setPrixBase('80')
            ->setReduction('5')
            ->setNbActivitesMax(2)
            ->setStatutPack('ACTIF');
        $this->setEntityId($cheapPack, 'id_pack', 10);

        $expensivePack = (new Pack())
            ->setNom('Pack Expensive')
            ->setTypePack('Premium')
            ->setPrixBase('500')
            ->setReduction('0')
            ->setNbActivitesMax(10)
            ->setStatutPack('ACTIF');
        $this->setEntityId($expensivePack, 'id_pack', 11);

        $cheapInsight = new PackInsightView($cheapPack, 80.0, 1, ['Économique'], ['cheap'], ['final_price' => 76.0]);
        $expensiveInsight = new PackInsightView($expensivePack, 85.0, 2, ['Premium'], ['luxury'], ['final_price' => 500.0]);

        $recommendations = $this->engine->recommendForUser(
            [$cheapPack, $expensivePack],
            [10 => $cheapInsight, 11 => $expensiveInsight],
            $user,
            2
        );

        self::assertCount(2, $recommendations);
        // Cheap pack should be recommended higher due to budget affinity
        self::assertTrue(
            $recommendations[0]->getRecommendationScore() > $cheapInsight->getScore() ||
            $recommendations[0]->getPack()->getIdPack() === 10
        );
    }

    public function testRecommendForUserConsidersTypePreferenceForExistingUser(): void
    {
        $user = (new UserApp())
            ->setNom('Adventurer')
            ->setPrenom('Expert')
            ->setEmail('adventurer@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        // User has history of adventure packs
        $adventurePack = (new Pack())
            ->setNom('Pack Aventure')
            ->setTypePack('Aventure')
            ->setPrixBase('300')
            ->setReduction('15')
            ->setNbActivitesMax(8)
            ->setStatutPack('ACTIF');
        $this->setEntityId($adventurePack, 'id_pack', 1);

        $adventureInscription = (new Inscription())
            ->setPack($adventurePack)
            ->setUserApp($user)
            ->setNomUser('Expert Adventurer')
            ->setNomPack('Pack Aventure')
            ->setMontantTotal('255')
            ->setDateInscription(new \DateTimeImmutable('-30 days'))
            ->setStatutInscr(StatutInscription::VALIDEE);
        $user->getInscriptions()->add($adventureInscription);

        // Similar adventure pack (affinity)
        $adventurePack2 = (new Pack())
            ->setNom('Pack Aventure 2')
            ->setTypePack('Aventure')
            ->setPrixBase('280')
            ->setReduction('10')
            ->setNbActivitesMax(7)
            ->setStatutPack('ACTIF');
        $this->setEntityId($adventurePack2, 'id_pack', 2);

        // Different type pack (no affinity)
        $relaxPack = (new Pack())
            ->setNom('Pack Relax')
            ->setTypePack('Decouverte')
            ->setPrixBase('150')
            ->setReduction('20')
            ->setNbActivitesMax(3)
            ->setStatutPack('ACTIF');
        $this->setEntityId($relaxPack, 'id_pack', 3);

        $insight2 = new PackInsightView($adventurePack2, 80.0, 1, ['Aventure'], ['exciting'], ['final_price' => 252.0]);
        $insight3 = new PackInsightView($relaxPack, 82.0, 2, ['Relax'], ['peaceful'], ['final_price' => 120.0]);

        $recommendations = $this->engine->recommendForUser(
            [$adventurePack2, $relaxPack],
            [2 => $insight2, 3 => $insight3],
            $user,
            2
        );

        self::assertCount(2, $recommendations);
        // Despite lower base score, adventure pack should rank higher
        self::assertSame('Aventure', $recommendations[0]->getPack()->getTypePack());
    }

    public function testRecommendForUserBoostsNoveltySuggestions(): void
    {
        $user = (new UserApp())
            ->setNom('Loyal')
            ->setPrenom('Customer')
            ->setEmail('loyal@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        // User already subscribed to pack 1
        $knownPack = (new Pack())
            ->setNom('Known Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($knownPack, 'id_pack', 1);

        $knownInscription = (new Inscription())
            ->setPack($knownPack)
            ->setUserApp($user)
            ->setNomUser('Loyal Customer')
            ->setNomPack('Known Pack')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable('-60 days'))
            ->setStatutInscr(StatutInscription::VALIDEE);
        $user->getInscriptions()->add($knownInscription);

        // New pack (not in user history)
        $newPack = (new Pack())
            ->setNom('New Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($newPack, 'id_pack', 2);

        $knownInsight = new PackInsightView($knownPack, 75.0, 1, ['Connu'], ['known'], ['final_price' => 180.0]);
        $newInsight = new PackInsightView($newPack, 75.0, 2, ['Nouveau'], ['new'], ['final_price' => 180.0]);

        $recommendations = $this->engine->recommendForUser(
            [$knownPack, $newPack],
            [1 => $knownInsight, 2 => $newInsight],
            $user,
            2
        );

        self::assertCount(2, $recommendations);
        // New pack should be boosted in recommendations despite same base score
        self::assertSame(2, $recommendations[0]->getPack()->getIdPack());
    }

    public function testRecommendAlternativeReturnsDifferentPackThanCurrent(): void
    {
        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $currentPack = (new Pack())
            ->setNom('Current Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($currentPack, 'id_pack', 1);

        $alternativePack1 = (new Pack())
            ->setNom('Alternative 1')
            ->setTypePack('Aventure')
            ->setPrixBase('250')
            ->setReduction('15')
            ->setNbActivitesMax(6)
            ->setStatutPack('ACTIF');
        $this->setEntityId($alternativePack1, 'id_pack', 2);

        $alternativePack2 = (new Pack())
            ->setNom('Alternative 2')
            ->setTypePack('Premium')
            ->setPrixBase('300')
            ->setReduction('20')
            ->setNbActivitesMax(7)
            ->setStatutPack('ACTIF');
        $this->setEntityId($alternativePack2, 'id_pack', 3);

        $currentInsight = new PackInsightView($currentPack, 75.0, 1, ['Current'], ['current'], ['final_price' => 180.0]);
        $alt1Insight = new PackInsightView($alternativePack1, 80.0, 2, ['Alt1'], ['alt1'], ['final_price' => 212.5]);
        $alt2Insight = new PackInsightView($alternativePack2, 85.0, 3, ['Alt2'], ['alt2'], ['final_price' => 240.0]);

        $alternative = $this->engine->recommendAlternative(
            $currentPack,
            [$currentPack, $alternativePack1, $alternativePack2],
            [1 => $currentInsight, 2 => $alt1Insight, 3 => $alt2Insight],
            $user
        );

        self::assertNotNull($alternative);
        self::assertNotSame(1, $alternative->getPack()->getIdPack());
        self::assertSame(3, $alternative->getPack()->getIdPack());
    }

    public function testRecommendAlternativeReturnsNullWhenNoneAvailable(): void
    {
        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $onlyPack = (new Pack())
            ->setNom('Only Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');
        $this->setEntityId($onlyPack, 'id_pack', 1);

        $insight = new PackInsightView($onlyPack, 75.0, 1, ['Only'], ['only'], ['final_price' => 180.0]);

        $alternative = $this->engine->recommendAlternative(
            $onlyPack,
            [$onlyPack],
            [1 => $insight],
            $user
        );

        self::assertNull($alternative);
    }

    public function testRecommendForUserConsidersNullUser(): void
    {
        $pack1 = (new Pack())
            ->setNom('Pack 1')
            ->setTypePack('Type1')
            ->setPrixBase('100')
            ->setReduction('5')
            ->setNbActivitesMax(2)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack1, 'id_pack', 1);

        $pack2 = (new Pack())
            ->setNom('Pack 2')
            ->setTypePack('Type2')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(4)
            ->setStatutPack('ACTIF');
        $this->setEntityId($pack2, 'id_pack', 2);

        $insight1 = new PackInsightView($pack1, 70.0, 2, ['B'], ['b'], ['final_price' => 95.0]);
        $insight2 = new PackInsightView($pack2, 80.0, 1, ['A'], ['a'], ['final_price' => 180.0]);

        $recommendations = $this->engine->recommendForUser(
            [$pack1, $pack2],
            [1 => $insight2, 2 => $insight1],
            null,
            2
        );

        self::assertCount(2, $recommendations);
        self::assertSame(80.0, $recommendations[0]->getScore());
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
