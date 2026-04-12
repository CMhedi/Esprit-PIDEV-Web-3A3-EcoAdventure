<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\PlanningRepository;
use App\Enum\StatutPlanning;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
#[ORM\Entity(repositoryClass: PlanningRepository::class)]
#[ORM\Table(name: 'planning')]
class Planning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_planning = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(
        min: 3,
        minMessage: "Le titre doit contenir au moins {{ limit }} caractères"
    )]
    private ?string $titre = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\NotBlank(message: "La description est obligatoire")]
    #[Assert\Length(
        min: 5,
        minMessage: "La description doit contenir au moins {{ limit }} caractères"
    )]
    private ?string $description = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: "La date de début est obligatoire")]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $date_debut = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Assert\NotBlank(message: "La date de fin est obligatoire")]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $date_fin = null;

    #[ORM\Column(enumType: StatutPlanning::class)]
    #[Assert\NotNull(message: "Le statut est obligatoire")]
    private ?StatutPlanning $statut = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'planning')]
    private Collection $seances;

    public function __construct()
    {
        $this->seances = new ArrayCollection();
    }

    // =========================
    // BONUS 🔥 VALIDATION MÉTIER
    // =========================
  #[Assert\Callback]
public function validateDates(ExecutionContextInterface $context): void
{
    $today = new \DateTime('today');

    // 🔴 Vérifier date début >= aujourd’hui
    if ($this->date_debut && $this->date_debut < $today) {
        $context->buildViolation("La date de début ne peut pas être dans le passé")
            ->atPath('date_debut')
            ->addViolation();
    }

    // 🔴 Vérifier date fin > date début
    if ($this->date_debut && $this->date_fin) {
        if ($this->date_fin <= $this->date_debut) {
            $context->buildViolation("La date de fin doit être après la date de début")
                ->atPath('date_fin')
                ->addViolation();
        }
    }
}

    // =========================
    // GETTERS / SETTERS
    // =========================

    public function getIdPlanning(): ?int { return $this->id_planning; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(?string $titre): self { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getDateDebut(): ?\DateTimeInterface { return $this->date_debut; }
    public function setDateDebut(?\DateTimeInterface $date): self
{
    $this->date_debut = $date;
    return $this;
}
    public function getDateFin(): ?\DateTimeInterface { return $this->date_fin; }
    public function setDateFin(?\DateTimeInterface $date): self
{
    $this->date_fin = $date;
    return $this;
}

    public function getStatut(): ?StatutPlanning { return $this->statut; }
    public function setStatut(StatutPlanning $statut): self { $this->statut = $statut; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(\DateTimeInterface $date): self { $this->created_at = $date; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updated_at; }
    public function setUpdatedAt(\DateTimeInterface $date): self { $this->updated_at = $date; return $this; }

    public function getSeances(): Collection { return $this->seances; }
    public function getDuree(): ?int
{
    if ($this->date_debut && $this->date_fin) {
        return $this->date_debut->diff($this->date_fin)->days;
    }

    return null;
}
}