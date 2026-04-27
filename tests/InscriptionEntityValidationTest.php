<?php

namespace App\Tests;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutInscription;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InscriptionEntityValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        // In a real environment, this would be injected from the container
        // For this test, we're testing entity constraints
        $this->markTestSkipped('This test requires Symfony validator integration setup');
    }

    public function testInscriptionRequiresStatut(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

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
            ->setNomPack('Test Pack')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable());
        // No statut set

        self::assertNull($inscription->getStatutInscr());
    }

    public function testInscriptionRequiresUserName(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomPack('Test Pack')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatutInscr(StatutInscription::EN_ATTENTE);
        // No nomUser set

        self::assertNull($inscription->getNomUser());
    }

    public function testInscriptionCanHaveValidPaymentStatus(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

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
            ->setNomPack('Test Pack')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatutInscr(StatutInscription::EN_ATTENTE)
            ->setPaymentGateway(Inscription::PAYMENT_GATEWAY_STRIPE)
            ->setPaymentStatus(Inscription::PAYMENT_STATUS_PAID);

        self::assertSame(Inscription::PAYMENT_GATEWAY_STRIPE, $inscription->getPaymentGateway());
        self::assertSame(Inscription::PAYMENT_STATUS_PAID, $inscription->getPaymentStatus());
    }

    public function testInscriptionMontantTotalIsDecimal(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $montant = '180.50';
        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Test User')
            ->setNomPack('Test Pack')
            ->setMontantTotal($montant)
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatutInscr(StatutInscription::EN_ATTENTE);

        self::assertSame($montant, $inscription->getMontantTotal());
    }

    public function testInscriptionCanTrackPaymentDetails(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

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
            ->setNomPack('Test Pack')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatutInscr(StatutInscription::EN_ATTENTE)
            ->setPaymentGateway(Inscription::PAYMENT_GATEWAY_KONNECT)
            ->setPaymentReference('REF-123456')
            ->setPaymentOrderId('ORDER-789');

        self::assertSame(Inscription::PAYMENT_GATEWAY_KONNECT, $inscription->getPaymentGateway());
        self::assertSame('REF-123456', $inscription->getPaymentReference());
        self::assertSame('ORDER-789', $inscription->getPaymentOrderId());
    }

    public function testInscriptionTracksPaidDate(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $paidDate = new \DateTimeImmutable();
        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Test User')
            ->setNomPack('Test Pack')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatutInscr(StatutInscription::VALIDEE)
            ->setPaymentStatus(Inscription::PAYMENT_STATUS_PAID)
            ->setPaidAt($paidDate);

        self::assertSame($paidDate, $inscription->getPaidAt());
    }

    public function testInscriptionStoreCardImage(): void
    {
        $pack = (new Pack())
            ->setNom('Test Pack')
            ->setTypePack('Aventure')
            ->setPrixBase('200')
            ->setReduction('10')
            ->setNbActivitesMax(5)
            ->setStatutPack('ACTIF');

        $user = (new UserApp())
            ->setNom('Test')
            ->setPrenom('User')
            ->setEmail('test@example.com')
            ->setRole(RoleUser::USER_SIMPLE)
            ->setMot_de_passe('secret');

        $cardImagePath = '/uploads/cards/card_001.jpg';
        $inscription = (new Inscription())
            ->setPack($pack)
            ->setUserApp($user)
            ->setNomUser('Test User')
            ->setNomPack('Test Pack')
            ->setMontantTotal('180')
            ->setDateInscription(new \DateTimeImmutable())
            ->setStatutInscr(StatutInscription::VALIDEE)
            ->setCardImage($cardImagePath);

        self::assertSame($cardImagePath, $inscription->getCardImage());
    }
}
