<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\StatutReservation;
use App\Repository\ReservationSeanceRepository;
use App\Enum\StatutPresence;
#[ORM\Entity(repositoryClass: ReservationSeanceRepository::class)]
#[ORM\Table(name: 'reservation_seance')]
class ReservationSeance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_reservation = null;

    public function getId_reservation(): ?int
    {
        return $this->id_reservation;
    }

    public function setId_reservation(int $id_reservation): self
    {
        $this->id_reservation = $id_reservation;
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



    #[ORM\Column(name: 'statut', type: 'string', length: 255)]
    private ?string $statut = null;

    public function getStatut(): ?StatutReservation
    {
        return $this->statut ? StatutReservation::tryFrom($this->statut) : null;
    }
    public function setStatut(?StatutReservation $statut): self
    {
        $this->statut = $statut?->value;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'reservationSeances')]
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

    #[ORM\ManyToOne(targetEntity: Seance::class, inversedBy: 'reservationSeances')]
    #[ORM\JoinColumn(name: 'id_seance', referencedColumnName: 'id_seance')]
    private ?Seance $seance = null;

    public function getSeance(): ?Seance
    {
        return $this->seance;
    }

    public function setSeance(?Seance $seance): self
    {
        $this->seance = $seance;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $google_event_id = null;

    public function getGoogle_event_id(): ?string
    {
        return $this->google_event_id;
    }

    public function setGoogle_event_id(?string $google_event_id): self
    {
        $this->google_event_id = $google_event_id;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $google_event_link = null;

    public function getGoogle_event_link(): ?string
    {
        return $this->google_event_link;
    }

    public function setGoogle_event_link(?string $google_event_link): self
    {
        $this->google_event_link = $google_event_link;
        return $this;
    }



    #[ORM\Column(type: 'string', length: 255)]
    private ?string $statutPresence = null;

    public function getStatutPresence(): ?StatutPresence
    {
        return $this->statutPresence ? StatutPresence::tryFrom($this->statutPresence) : null;
    }
    public function setStatutPresence(?StatutPresence $statut): self
    {
        $this->statutPresence = $statut?->value;
        return $this;
    }

}
