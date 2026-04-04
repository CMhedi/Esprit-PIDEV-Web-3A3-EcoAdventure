<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\StatutReservationActivite;
use App\Repository\ReservationActiviteRepository;

#[ORM\Entity(repositoryClass: ReservationActiviteRepository::class)]
#[ORM\Table(name: 'reservation_activite')]
class ReservationActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_res_act = null;

    public function getId_res_act(): ?int
    {
        return $this->id_res_act;
    }

    public function setId_res_act(int $id_res_act): self
    {
        $this->id_res_act = $id_res_act;
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



#[ORM\Column(enumType: StatutReservationActivite::class)]
private ?StatutReservationActivite $statut_res = null;

    public function getStatut_res(): ?StatutReservationActivite
    {
        return $this->statut_res;
    }

    public function setStatut_res(StatutReservationActivite $statut_res): self
    {
        $this->statut_res = $statut_res;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $nb_personnes = null;

    public function getNb_personnes(): ?int
    {
        return $this->nb_personnes;
    }

    public function setNb_personnes(int $nb_personnes): self
    {
        $this->nb_personnes = $nb_personnes;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'reservationActivites')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user')]
    private ?UserApp $userApp = null;

    public function getUserApp(): ?UserApp
    {
        return $this->userApp;
    }

    public function setUserApp(?UserApp $userApp): self
    {
        $this->userApp = $userApp;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'reservationActivites')]
    #[ORM\JoinColumn(name: 'id_activite', referencedColumnName: 'id_activite')]
    private ?Activite $activite = null;

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
