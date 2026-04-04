<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\PackRepository;
use App\Enum\TypePack;
use App\Enum\StatutPack;

#[ORM\Entity(repositoryClass: PackRepository::class)]
#[ORM\Table(name: 'pack')]
class Pack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_pack = null;

    #[ORM\Column(type: 'string', length: 120)]
    private ?string $nom = null;

    #[ORM\Column(enumType: TypePack::class)]
    private ?TypePack $type_pack = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prix_base = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $reduction = null;

    #[ORM\Column(type: 'integer')]
    private ?int $nb_activites_max = null;

    #[ORM\Column(enumType: StatutPack::class)]
    private ?StatutPack $statut_pack = null;

    #[ORM\OneToMany(targetEntity: Activite::class, mappedBy: 'pack')]
    private Collection $activites;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getTypePack(): ?TypePack
    {
        return $this->type_pack;
    }

    public function setTypePack(TypePack $type_pack): self
    {
        $this->type_pack = $type_pack;
        return $this;
    }

    public function getPrixBase(): ?string
    {
        return $this->prix_base;
    }

    public function setPrixBase(string $prix_base): self
    {
        $this->prix_base = $prix_base;
        return $this;
    }

    public function getReduction(): ?string
    {
        return $this->reduction;
    }

    public function setReduction(string $reduction): self
    {
        $this->reduction = $reduction;
        return $this;
    }

    public function getNbActivitesMax(): ?int
    {
        return $this->nb_activites_max;
    }

    public function setNbActivitesMax(int $nb): self
    {
        $this->nb_activites_max = $nb;
        return $this;
    }

    public function getStatutPack(): ?StatutPack
    {
        return $this->statut_pack;
    }

    public function setStatutPack(StatutPack $statut): self
    {
        $this->statut_pack = $statut;
        return $this;
    }

    public function getActivites(): Collection
    {
        return $this->activites;
    }

    public function getInscriptions(): Collection
    {
        return $this->inscriptions;
    }
}

