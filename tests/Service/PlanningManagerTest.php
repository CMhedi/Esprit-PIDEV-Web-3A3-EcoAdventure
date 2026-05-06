<?php
namespace App\Tests\Service;

use App\Entity\Planning;
use App\Enum\StatutPlanning;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PlanningManagerTest extends KernelTestCase
{
    private function getValidator(): ValidatorInterface
    {
        self::bootKernel();
        return static::getContainer()->get(ValidatorInterface::class);
    }

    public function testPlanningValide(): void
    {
        $planning = new Planning();
        $planning->setTitre('Planning Avril');
        $planning->setDescription('Planning des activités sportives');
        $planning->setDateDebut(new \DateTime('+1 day'));
        $planning->setDateFin(new \DateTime('+5 days'));
        $planning->setStatut(StatutPlanning::ACTIF);

        $errors = $this->getValidator()->validate($planning);

        $this->assertCount(0, $errors);
    }

    public function testPlanningSansTitre(): void
    {
        $planning = new Planning();
        $planning->setDescription('desc');
        $planning->setDateDebut(new \DateTime('+1 day'));
        $planning->setDateFin(new \DateTime('+5 days'));
        $planning->setStatut(StatutPlanning::ACTIF);

        $errors = $this->getValidator()->validate($planning);

        $this->assertGreaterThan(0, count($errors));
    }

    public function testPlanningDateDebutPassee(): void
    {
        $planning = new Planning();
        $planning->setTitre('Test');
        $planning->setDescription('description valide');
        $planning->setDateDebut(new \DateTime('-1 day'));
        $planning->setDateFin(new \DateTime('+2 days'));
        $planning->setStatut(StatutPlanning::ACTIF);

        $errors = $this->getValidator()->validate($planning);

        $this->assertGreaterThan(0, count($errors));
    }

    public function testPlanningDateFinInvalide(): void
    {
        $planning = new Planning();
        $planning->setTitre('Test');
        $planning->setDescription('description valide');
        $planning->setDateDebut(new \DateTime('+5 days'));
        $planning->setDateFin(new \DateTime('+1 day'));
        $planning->setStatut(StatutPlanning::ACTIF);

        $errors = $this->getValidator()->validate($planning);

        $this->assertGreaterThan(0, count($errors));
    }

    public function testPlanningSansStatut(): void
    {
        $planning = new Planning();
        $planning->setTitre('Test');
        $planning->setDescription('description valide');
        $planning->setDateDebut(new \DateTime('+1 day'));
        $planning->setDateFin(new \DateTime('+2 days'));

        $errors = $this->getValidator()->validate($planning);

        $this->assertGreaterThan(0, count($errors));
    }
}