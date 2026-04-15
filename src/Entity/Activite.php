<?php

namespace App\Entity;

use App\Enum\CategorieAct;
use App\Enum\NiveauAct;
use App\Enum\Statut;
use App\Enum\TypeActivite;
use App\Repository\ActiviteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Table(name: 'activite')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_activite = null;

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: 'Le nom doit contenir au moins 3 caracteres.',
        maxMessage: 'Le nom ne doit pas depasser 120 caracteres.'
    )]
    #[Assert\Regex(
        pattern: "/^[\\p{L}\\s'-]+$/u",
        message: 'Le nom doit contenir uniquement des lettres.'
    )]
    #[ORM\Column(type: 'string', length: 120)]
    private ?string $nom = null;

    #[Assert\NotNull(message: "Le type d'activite est obligatoire.")]
    #[ORM\Column(enumType: TypeActivite::class)]
    private ?TypeActivite $type_activite = null;

    #[Assert\NotNull(message: 'La categorie est obligatoire.')]
    #[ORM\Column(enumType: CategorieAct::class)]
    private ?CategorieAct $categorie_act = null;

    #[Assert\NotNull(message: 'Le niveau est obligatoire.')]
    #[ORM\Column(enumType: NiveauAct::class)]
    private ?NiveauAct $niveau_act = null;

    #[Assert\NotNull(message: 'Le prix est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit etre positif.')]
    #[Assert\Range(
        min: 1,
        max: 10000,
        notInRangeMessage: 'Le prix doit etre entre {{ min }} et {{ max }} DT.'
    )]
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $prix = null;

    #[Assert\NotNull(message: 'Le statut est obligatoire.')]
    #[ORM\Column(enumType: Statut::class)]
    private ?Statut $statut = null;

    #[Assert\NotBlank(message: "L'image est obligatoire.")]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image_url = null;

    #[Assert\NotNull(message: 'Le pack est obligatoire et doit etre valide.')]
    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'activites')]
    #[ORM\JoinColumn(name: 'id_pack', referencedColumnName: 'id_pack', nullable: true)]
    private ?Pack $pack = null;

    #[ORM\OneToMany(targetEntity: ReservationActivite::class, mappedBy: 'activite')]
    private Collection $reservationActivites;

    public function __construct()
    {
        $this->reservationActivites = new ArrayCollection();
    }

    public function getIdActivite(): ?int
    {
        return $this->id_activite;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getTypeActivite(): ?TypeActivite
    {
        return $this->type_activite;
    }

    public function setTypeActivite(?TypeActivite $type): self
    {
        $this->type_activite = $type;

        return $this;
    }

    public function getCategorieAct(): ?CategorieAct
    {
        return $this->categorie_act;
    }

    public function setCategorieAct(?CategorieAct $categorie): self
    {
        $this->categorie_act = $categorie;

        return $this;
    }

    public function getNiveauAct(): ?NiveauAct
    {
        return $this->niveau_act;
    }

    public function setNiveauAct(?NiveauAct $niveau): self
    {
        $this->niveau_act = $niveau;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $prix): self
    {
        $this->prix = $prix;

        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $url): self
    {
        $this->image_url = $url;

        return $this;
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self
    {
        $this->pack = $pack;

        return $this;
    }

    public function getReservationActivites(): Collection
    {
        return $this->reservationActivites;
    }
}
