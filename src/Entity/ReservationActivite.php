<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Enum\StatutReservationActivite;
use App\Repository\ReservationActiviteRepository;

#[ORM\Entity(repositoryClass: ReservationActiviteRepository::class)]
#[ORM\Table(name: 'reservation_activite')]
class ReservationActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_res_act', type: 'integer')]
    private ?int $id_res_act = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date_reservation = null;

    #[ORM\Column(enumType: StatutReservationActivite::class)]
    private ?StatutReservationActivite $statut_res = null;

    #[ORM\Column(type: 'integer')]
    private ?int $nb_personnes = null;

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'reservationActivites')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private ?UserApp $userApp = null;

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'reservationActivites')]
    #[ORM\JoinColumn(name: 'id_activite', referencedColumnName: 'id_activite', nullable: false)]
    private ?Activite $activite = null;

    public function getIdResAct(): ?int
    {
        return $this->id_res_act;
    }

    public function getDateReservation(): ?\DateTimeInterface
    {
        return $this->date_reservation;
    }

    public function setDateReservation(\DateTimeInterface $date_reservation): self
    {
        $this->date_reservation = $date_reservation;
        return $this;
    }

    public function getStatutRes(): ?StatutReservationActivite
    {
        return $this->statut_res;
    }

    public function setStatutRes(StatutReservationActivite $statut_res): self
    {
        $this->statut_res = $statut_res;
        return $this;
    }

    public function getNbPersonnes(): ?int
    {
        return $this->nb_personnes;
    }

    public function setNbPersonnes(int $nb_personnes): self
    {
        $this->nb_personnes = $nb_personnes;
        return $this;
    }

    public function getUserApp(): ?UserApp
    {
        return $this->userApp;
    }

    public function setUserApp(?UserApp $userApp): self
    {
        $this->userApp = $userApp;
        return $this;
    }

    public function getActivite(): ?Activite
    {
        return $this->activite;
    }

    public function setActivite(?Activite $activite): self
    {
        $this->activite = $activite;
        return $this;
    }
}