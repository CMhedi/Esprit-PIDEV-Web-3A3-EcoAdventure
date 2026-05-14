<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Repository\ReservationEvenementRepository;
use App\Enum\StatutReservationEvenement;
#[ORM\Entity(repositoryClass: ReservationEvenementRepository::class)]
#[ORM\Table(name: 'reservation_evenement')]
class ReservationEvenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_res_evt = null;

    public function getId_res_evt(): ?int
    {
        return $this->id_res_evt;
    }

    public function setId_res_evt(int $id_res_evt): self
    {
        $this->id_res_evt = $id_res_evt;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_reservation = null;

    public function getDate_reservation(): ?\DateTimeInterface
    {
        return $this->date_reservation;
    }

    public function setDate_reservation(\DateTimeInterface $date_reservation): self
    {
        $this->date_reservation = $date_reservation;
        return $this;
    }



    #[ORM\Column(name: 'statut_res', type: 'string', length: 255, enumType: StatutReservationEvenement::class)]
    private ?StatutReservationEvenement $statut_res = null;

    public function getStatut_res(): ?StatutReservationEvenement
    {
        return $this->statut_res;
    }

    public function getStatutReservationEvenement(): ?StatutReservationEvenement
    {
        return $this->getStatut_res();
    }

    public function setStatut_res(?StatutReservationEvenement $statut): self
    {
        $this->statut_res = $statut;
        return $this;
    }



    #[ORM\Column(type: 'boolean', options: ["default" => false])]
    private bool $is_notified_availability = false;

    public function isNotifiedAvailability(): bool
    {
        return $this->is_notified_availability;
    }

    public function setIsNotifiedAvailability(bool $is_notified_availability): self
    {
        $this->is_notified_availability = $is_notified_availability;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $nb_billets = null;

    public function getNb_billets(): ?int
    {
        return $this->nb_billets;
    }

    public function setNb_billets(int $nb_billets): self
    {
        $this->nb_billets = $nb_billets;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'reservationEvenements')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private UserApp $userApp;

    public function getUserApp(): UserApp
    {
        return $this->userApp;
    }

    public function setUserApp(UserApp $userApp): self
    {
        $this->userApp = $userApp;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Evenement::class, inversedBy: 'reservationEvenements')]
    #[ORM\JoinColumn(name: 'id_evenement', referencedColumnName: 'id_evenement', nullable: false, onDelete: 'CASCADE')]
    private Evenement $evenement;

    public function getEvenement(): Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(Evenement $evenement): self
    {
        $this->evenement = $evenement;
        return $this;
    }

}
