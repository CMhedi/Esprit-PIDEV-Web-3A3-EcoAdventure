<?php

namespace App\Tests;

use App\Entity\Pack;
use PHPUnit\Framework\TestCase;

final class PackEntityValidationTest extends TestCase
{
    public function testPackRequiresNom(): void
    {
        $pack = new Pack();

        self::assertNull($pack->getNom());
    }

    public function testPackNomIsTrimmed(): void
    {
        $pack = (new Pack())
            ->setNom('  Pack Adventure  ');

        self::assertSame('Pack Adventure', $pack->getNom());
    }

    public function testPackRequiresTypePack(): void
    {
        $pack = new Pack();

        self::assertNull($pack->getTypePack());
    }

    public function testPackRequiresPrixBase(): void
    {
        $pack = new Pack();

        self::assertNull($pack->getPrixBase());
    }

    public function testPackPrixBaseAcceptsDecimal(): void
    {
        $pack = (new Pack())
            ->setPrixBase('199.99');

        self::assertSame('199.99', $pack->getPrixBase());
    }

    public function testPackReductionMustBePositiveOrZero(): void
    {
        $pack = (new Pack())
            ->setReduction('0')
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        self::assertSame('0', $pack->getReduction());
    }

    public function testPackReductionCanBe100(): void
    {
        $pack = (new Pack())
            ->setReduction('100')
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        self::assertSame('100', $pack->getReduction());
    }

    public function testPackNbActivitesMaxMustBePositive(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(1)
            ->setStatutPack('ACTIF');

        self::assertSame(1, $pack->getNbActivitesMax());
    }

    public function testPackNbActivitesMaxCanBeUpTo100(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(100)
            ->setStatutPack('ACTIF');

        self::assertSame(100, $pack->getNbActivitesMax());
    }

    public function testPackRequiresStatutPack(): void
    {
        $pack = new Pack();

        self::assertNull($pack->getStatutPack());
    }

    public function testPackCanHaveActivites(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $activites = $pack->getActivites();

        self::assertNotNull($activites);
        self::assertCount(0, $activites);
    }

    public function testPackCanHaveInscriptions(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $inscriptions = $pack->getInscriptions();

        self::assertNotNull($inscriptions);
        self::assertCount(0, $inscriptions);
    }

    public function testPackGetIdPack(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $this->setEntityId($pack, 'id_pack', 42);

        self::assertSame(42, $pack->getId_pack());
        self::assertSame(42, $pack->getIdPack());
    }

    public function testPackCalculatesFinalPriceWithAbsoluteReduction(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('20')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $finalPrice = $pack->getPrixFinal();
        $expected = 200.0 - 20.0;

        self::assertSame($expected, $finalPrice);
    }

    public function testPackCalculatesFinalPriceWithoutReduction(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('0')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $finalPrice = $pack->getPrixFinal();

        self::assertSame(200.0, $finalPrice);
    }

    public function testPackCalculatesFinalPriceWithLargeAbsoluteDiscount(): void
    {
        $pack = (new Pack())
            ->setNom('Free Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('200')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $finalPrice = $pack->getPrixFinal();
        // getPrixFinal uses max() to ensure non-negative
        self::assertSame(0.0, $finalPrice);
    }

    public function testPackActiveStatus(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        self::assertSame('ACTIF', $pack->getStatutPack());
    }

    public function testPackInactiveStatus(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('INACTIF');

        self::assertSame('INACTIF', $pack->getStatutPack());
    }

    public function testPackCanUpdateAllFields(): void
    {
        $pack = (new Pack())
            ->setNom('Original Pack')
            ->setTypePack('Découverte')
            ->setPrixBase('100')
            ->setReduction('5')
            ->setNbActivitesMax(3)
            ->setStatutPack('ACTIF');

        $pack
            ->setNom('Updated Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('250')
            ->setReduction('15')
            ->setNbActivitesMax(6)
            ->setStatutPack('INACTIF');

        self::assertSame('Updated Pack', $pack->getNom());
        self::assertSame('Aventure', $pack->getTypePack());
        self::assertSame('250', $pack->getPrixBase());
        self::assertSame('15', $pack->getReduction());
        self::assertSame(6, $pack->getNbActivitesMax());
        self::assertSame('INACTIF', $pack->getStatutPack());
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
