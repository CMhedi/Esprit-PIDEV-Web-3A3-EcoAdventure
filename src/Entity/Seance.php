<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\SeanceRepository;
use App\Enum\StatutSeance;
use App\Entity\Planning;
use App\Entity\UserApp;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\Table(name: 'seance')]
class Seance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idSeance = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: "Nom obligatoire")]
    #[Assert\Length(min: 3, minMessage: "Minimum 3 caractères")]
    private ?string $nom = null;

    #[ORM\Column(name: 'date_seance', type: 'date')]
    #[Assert\NotBlank(message: "Date obligatoire")]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $dateSeance = null;

    #[ORM\Column(name: 'heure_debut', type: 'time')]
    #[Assert\NotBlank(message: "Heure début obligatoire")]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(name: 'heure_fin', type: 'time')]
    #[Assert\NotBlank(message: "Heure fin obligatoire")]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Capacité obligatoire")]
    #[Assert\Positive(message: "Capacité doit être positive")]
    private ?int $capacite = null;

    #[ORM\Column(type: 'string')]
    #[Assert\NotNull(message: "Statut obligatoire")]
    private ?string $statutSeance = null;

    #[ORM\ManyToOne(targetEntity: Planning::class, inversedBy: 'seances')]
    #[ORM\JoinColumn(name: 'id_planning', referencedColumnName: 'id_planning', nullable: false)]
    private ?Planning $planning = null;

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'seances')]
    #[ORM\JoinColumn(name: 'id_coach', referencedColumnName: 'id_user', nullable: false)]
    private ?UserApp $coach = null;

    #[ORM\OneToMany(targetEntity: ReservationSeance::class, mappedBy: 'seance')]
    private Collection $reservationSeances;

    public function __construct()
    {
        $this->reservationSeances = new ArrayCollection();
    }

    // =========================
    // GETTERS / SETTERS
    // =========================

    public function getIdSeance(): ?int { return $this->idSeance; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getDateSeance(): ?\DateTimeInterface { return $this->dateSeance; }
public function setDateSeance(?\DateTimeInterface $date): self
{
    $this->dateSeance = $date;
    return $this;
}

    public function getHeureDebut(): ?\DateTimeInterface { return $this->heureDebut; }
public function setHeureDebut(?\DateTimeInterface $heure): self
{
    $this->heureDebut = $heure;
    return $this;
}

    public function getHeureFin(): ?\DateTimeInterface { return $this->heureFin; }
public function setHeureFin(?\DateTimeInterface $heure): self
{
    $this->heureFin = $heure;
    return $this;
}

    public function getCapacite(): ?int { return $this->capacite; }
    public function setCapacite(int $capacite): self { $this->capacite = $capacite; return $this; }

    public function getStatutSeance(): ?StatutSeance { return $this->statutSeance !== null ? StatutSeance::tryFrom($this->statutSeance) : null; }
    public function setStatutSeance(StatutSeance|string $statut): self { $this->statutSeance = $statut instanceof StatutSeance ? $statut->value : $statut; return $this; }

    public function getPlanning(): ?Planning { return $this->planning; }
    public function setPlanning(?Planning $planning): self { $this->planning = $planning; return $this; }

    public function getCoach(): ?UserApp { return $this->coach; }
    public function setCoach(?UserApp $coach): self { $this->coach = $coach; return $this; }

    public function getReservationSeances(): Collection { return $this->reservationSeances; }

    // =========================
    // VALIDATION MÉTIER 🔥
    // =========================

    #[Assert\Callback]
    public function validateSeance(ExecutionContextInterface $context): void
    {
        if (!$this->planning) return;

        $today = new \DateTime('today');

        // ❌ date passée
        if ($this->dateSeance && $this->dateSeance < $today) {
            $context->buildViolation("Séance dans le passé interdite")
                ->atPath('dateSeance')
                ->addViolation();
        }

        // ❌ planning archivé
        if ($this->planning->getStatut()?->value === 'ARCHIVE') {
            $context->buildViolation("Planning archivé")
                ->addViolation();
        }

        // ❌ planning expiré
        if ($this->planning->getDateFin() < $today) {
            $context->buildViolation("Planning expiré")
                ->addViolation();
        }

       // ❌ hors interval (VERSION CORRIGÉE)
if ($this->dateSeance && $this->planning->getDateDebut() && $this->planning->getDateFin()) {

    $dateSeance = \DateTimeImmutable::createFromInterface($this->dateSeance)->setTime(0, 0);
    $dateDebut = \DateTimeImmutable::createFromInterface($this->planning->getDateDebut())->setTime(0, 0);
    $dateFin = \DateTimeImmutable::createFromInterface($this->planning->getDateFin())->setTime(23, 59, 59);

    if ($dateSeance < $dateDebut || $dateSeance > $dateFin) {
        $context->buildViolation("La date doit être comprise entre le début et la fin du planning")
            ->atPath('dateSeance')
            ->addViolation();
    }
}

        // ❌ heure fin < début
        if ($this->heureDebut && $this->heureFin) {
            if ($this->heureFin <= $this->heureDebut) {
                $context->buildViolation("Heure fin doit être après début")
                    ->atPath('heureFin')
                    ->addViolation();
            }
        }
    }
}
