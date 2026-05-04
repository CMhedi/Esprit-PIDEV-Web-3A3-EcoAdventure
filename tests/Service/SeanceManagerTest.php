<?php
namespace App\Tests\Service;

use App\Entity\Seance;
use App\Service\SeanceManager;
use PHPUnit\Framework\TestCase;

class SeanceManagerTest extends TestCase
{
    public function testSeanceValide(): void
    {
        $seance = new Seance();
        $seance->setDateSeance(new \DateTime('2026-04-11'));
        $seance->setHeureDebut(new \DateTime('09:00'));
        $seance->setHeureFin(new \DateTime('10:00'));
        $seance->setCapacite(20);

        $manager = new SeanceManager();

        $this->assertTrue($manager->validate($seance));
    }

    public function testHeureFinAvantDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $seance = new Seance();
        $seance->setDateSeance(new \DateTime('2026-04-11'));
        $seance->setHeureDebut(new \DateTime('10:00'));
        $seance->setHeureFin(new \DateTime('09:00'));
        $seance->setCapacite(20);

        (new SeanceManager())->validate($seance);
    }

    public function testCapaciteInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $seance = new Seance();
        $seance->setDateSeance(new \DateTime('2026-04-11'));
        $seance->setHeureDebut(new \DateTime('09:00'));
        $seance->setHeureFin(new \DateTime('10:00'));
        $seance->setCapacite(0);

        (new SeanceManager())->validate($seance);
    }
}
