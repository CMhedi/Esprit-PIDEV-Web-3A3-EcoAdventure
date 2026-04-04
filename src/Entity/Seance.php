<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\SeanceRepository;
use App\Enum\StatutSeance;
use App\Entity\Planning;
use App\Entity\UserApp;
#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\Table(name: 'seance')]
class Seance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_seance = null;

    public function getId_seance(): ?int
    {
        return $this->id_seance;
    }

    public function setId_seance(int $id_seance): self
    {
        $this->id_seance = $id_seance;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_seance = null;

    public function getDate_seance(): ?\DateTimeInterface
    {
        return $this->date_seance;
    }

    public function setDate_seance(\DateTimeInterface $date_seance): self
    {
        $this->date_seance = $date_seance;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: false)]
    private ?string $heure_debut = null;

    public function getHeure_debut(): ?string
    {
        return $this->heure_debut;
    }

    public function setHeure_debut(string $heure_debut): self
    {
        $this->heure_debut = $heure_debut;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: false)]
    private ?string $heure_fin = null;

    public function getHeure_fin(): ?string
    {
        return $this->heure_fin;
    }

    public function setHeure_fin(string $heure_fin): self
    {
        $this->heure_fin = $heure_fin;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $capacite = null;

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): self
    {
        $this->capacite = $capacite;
        return $this;
    }



#[ORM\Column(enumType: StatutSeance::class)]
private ?StatutSeance $statut_seance = null;

    public function getStatut_seance(): ?StatutSeance
    {
        return $this->statut_seance;
    }

    public function setStatut_seance(StatutSeance $statut_seance): self
    {
        $this->statut_seance = $statut_seance;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Planning::class, inversedBy: 'seances')]
    #[ORM\JoinColumn(name: 'id_planning', referencedColumnName: 'id_planning')]
    private ?Planning $planning = null;

    public function getPlanning(): ?Planning
    {
        return $this->planning;
    }

    public function setPlanning(?Planning $planning): self
    {
        $this->planning = $planning;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'seances')]
    #[ORM\JoinColumn(name: 'id_coach', referencedColumnName: 'id_user')]
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

#[ORM\Column(type: 'string', length: 100)]
private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ReservationSeance::class, mappedBy: 'seance')]
    private Collection $reservationSeances;

    /**
     * @return Collection<int, ReservationSeance>
     */
    public function getReservationSeances(): Collection
    {
        if (!$this->reservationSeances instanceof Collection) {
            $this->reservationSeances = new ArrayCollection();
        }
        return $this->reservationSeances;
    }

    public function addReservationSeance(ReservationSeance $reservationSeance): self
    {
        if (!$this->getReservationSeances()->contains($reservationSeance)) {
            $this->getReservationSeances()->add($reservationSeance);
        }
        return $this;
    }

    public function removeReservationSeance(ReservationSeance $reservationSeance): self
    {
        $this->getReservationSeances()->removeElement($reservationSeance);
        return $this;
    }

}
