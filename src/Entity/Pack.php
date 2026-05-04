<?php

namespace App\Entity;

use App\Repository\PackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PackRepository::class)]
#[ORM\Table(name: 'pack')]
class Pack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_pack', type: 'integer')]
    private ?int $id_pack = null;

    #[Assert\NotBlank(message: 'Le nom du pack est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: 'Le nom du pack doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom du pack ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[ORM\Column(name: 'nom', type: 'string', length: 120)]
    private ?string $nom = null;

    #[Assert\NotBlank(message: 'Le type du pack est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le type du pack ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[ORM\Column(name: 'type_pack', type: 'string', length: 255)]
    private ?string $type_pack = null;

    #[Assert\NotBlank(message: 'Le prix de base est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le prix de base doit être positif ou nul.')]
    #[ORM\Column(name: 'prix_base', type: 'decimal', precision: 10, scale: 2)]
    private ?string $prix_base = null;

    #[Assert\NotBlank(message: 'La réduction est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'La réduction doit être positive ou nulle.')]
    #[Assert\LessThanOrEqual(
        value: 100,
        message: 'La réduction ne doit pas dépasser 100.'
    )]
    #[ORM\Column(name: 'reduction', type: 'decimal', precision: 10, scale: 2)]
    private ?string $reduction = null;

    #[Assert\NotNull(message: 'Le nombre maximum d’activités est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre maximum d’activités doit être supérieur à 0.')]
    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: 'Le nombre maximum d’activités doit être entre {{ min }} et {{ max }}.'
    )]
    #[ORM\Column(name: 'nb_activites_max', type: 'integer')]
    private ?int $nb_activites_max = null;

    #[Assert\NotBlank(message: 'Le statut du pack est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le statut du pack ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[ORM\Column(name: 'statut_pack', type: 'string', length: 255)]
    private ?string $statut_pack = null;

    /**
     * @var Collection<int, Activite>
     */
    #[ORM\OneToMany(targetEntity: Activite::class, mappedBy: 'pack')]
    private Collection $activites;

    /**
     * @var Collection<int, Inscription>
     */
    #[ORM\OneToMany(targetEntity: Inscription::class, mappedBy: 'pack')]
    private Collection $inscriptions;

    public function __construct()
    {
        $this->activites = new ArrayCollection();
        $this->inscriptions = new ArrayCollection();
    }

    public function getId_pack(): ?int
    {
        return $this->id_pack;
    }

    public function getIdPack(): ?int
    {
        return $this->id_pack;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom !== null ? trim($nom) : null;
        return $this;
    }

    public function getTypePack(): ?string
    {
        return $this->type_pack;
    }

    public function setTypePack(?string $type_pack): self
    {
        $this->type_pack = $type_pack !== null ? trim($type_pack) : null;
        return $this;
    }

    public function getPrixBase(): ?string
    {
        return $this->prix_base;
    }

    public function setPrixBase(?string $prix_base): self
    {
        $this->prix_base = $prix_base;
        return $this;
    }

    public function getReduction(): ?string
    {
        return $this->reduction;
    }

    public function setReduction(?string $reduction): self
    {
        $this->reduction = $reduction;
        return $this;
    }

    public function getNbActivitesMax(): ?int
    {
        return $this->nb_activites_max;
    }

    public function setNbActivitesMax(?int $nb_activites_max): self
    {
        $this->nb_activites_max = $nb_activites_max;
        return $this;
    }

    public function getStatutPack(): ?string
    {
        return $this->statut_pack;
    }

    public function setStatutPack(?string $statut_pack): self
    {
        $this->statut_pack = $statut_pack !== null ? trim($statut_pack) : null;
        return $this;
    }

    /**
     * @return Collection<int, Activite>
     */
    public function getActivites(): Collection
    {
        return $this->activites;
    }

    /**
     * @return Collection<int, Inscription>
     */
    public function getInscriptions(): Collection
    {
        return $this->inscriptions;
    }

    public function getPrixFinal(): float
    {
        $prix = (float) $this->prix_base;
        $reduction = (float) $this->reduction;

        return max($prix - $reduction, 0);
    }

    public function __toString(): string
    {
        return $this->nom ?? 'Pack';
    }
}

