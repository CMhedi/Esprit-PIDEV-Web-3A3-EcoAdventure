<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\CategorieEvenement;
use App\Repository\EvenementRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_evenement = null;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(min: 5, minMessage: "Le titre doit faire au moins {{ limit }} caract├¿res.")]
    private ?string $titre = null;

    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    private ?string $description = null;

    #[ORM\Column(enumType: CategorieEvenement::class)]
    #[Assert\NotNull(message: "Veuillez choisir une cat├®gorie.")]
    private ?CategorieEvenement $categorie_evt = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotBlank(message: "La date est obligatoire.")]
    #[Assert\GreaterThan("today", message: "La date de l'├®v├®nement doit ├¬tre dans le futur.")]
    private ?\DateTimeInterface $date_event = null;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank(message: "Le lieu est obligatoire.")]
    private ?string $lieu = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Le nombre de places est obligatoire.")]
    #[Assert\Positive(message: "Le nombre de places doit ├¬tre positif.")]
    private ?int $nb_places = null;

    #[ORM\Column(type: 'integer', options: ["default" => 10])]
    #[Assert\PositiveOrZero(message: "La limite de la liste d'attente doit ├¬tre positive ou z├®ro.")]
    private int $limite_attente = 10;

    #[ORM\Column(type: 'float', options: ["default" => 0.0])]
    #[Assert\NotBlank(message: "Le prix est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le prix doit ├¬tre positif.")]
    private float $prix = 0.0;

    public function getPrix(): float { return $this->prix; }
    public function setPrix(float $prix): self { $this->prix = $prix; return $this; }

    public function getId_evenement(): ?int { return $this->id_evenement; }
    public function setId_evenement(int $id): self { $this->id_evenement = $id; return $this; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $desc): self { $this->description = $desc; return $this; }
    public function getCategorie_evt(): ?CategorieEvenement { return $this->categorie_evt; }
    public function setCategorie_evt(CategorieEvenement $cat): self { $this->categorie_evt = $cat; return $this; }
    public function getCategorieEvt(): ?CategorieEvenement { return $this->categorie_evt; }
    public function setCategorieEvt(CategorieEvenement $cat): self { $this->categorie_evt = $cat; return $this; }
    public function getDate_event(): ?\DateTimeInterface { return $this->date_event; }
    public function setDate_event(\DateTimeInterface $date): self { $this->date_event = $date; return $this; }
    public function getDateEvent(): ?\DateTimeInterface { return $this->date_event; }
    public function setDateEvent(\DateTimeInterface $date): self { $this->date_event = $date; return $this; }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getNb_places(): ?int
    {
        return $this->nb_places;
    }

    public function setNb_places(int $nb_places): self
    {
        $this->nb_places = $nb_places;
        return $this;
    }

    public function getLimite_attente(): int
    {
        return $this->limite_attente;
    }

    public function setLimite_attente(int $limite): self
    {
        $this->limite_attente = $limite;
        return $this;
    }

    public function getLimiteAttente(): int
    {
        return $this->limite_attente;
    }

    public function setLimiteAttente(int $limite): self
    {
        $this->limite_attente = $limite;
        return $this;
    }

    public function getNbPlaces(): ?int { return $this->nb_places; }
    public function setNbPlaces(int $nb): self { $this->nb_places = $nb; return $this; }

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image_url = null;

    public function getImage_url(): ?string
    {
        return $this->image_url;
    }

    public function setImage_url(?string $image_url): self
    {
        $this->image_url = $image_url;
        return $this;
    }

    public function getImageUrl(): ?string { return $this->image_url; }
    public function setImageUrl(?string $url): self { $this->image_url = $url; return $this; }

    #[ORM\OneToMany(targetEntity: ReservationEvenement::class, mappedBy: 'evenement', cascade: ['remove'], orphanRemoval: true)]
    /** @var Collection<int, ReservationEvenement> */
    private Collection $reservationEvenements;

    #[ORM\OneToMany(targetEntity: EventRating::class, mappedBy: 'evenement', cascade: ['remove'])]
    /** @var Collection<int, EventRating> */
    private Collection $ratings;

    public function __construct()
    {
        $this->reservationEvenements = new ArrayCollection();
        $this->ratings = new ArrayCollection();
    }

    /**
     * @return Collection<int, ReservationEvenement>
     */
    public function getReservationEvenements(): Collection
    {
        return $this->reservationEvenements;
    }

    /**
     * @return Collection<int, EventRating>
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function getAverageRating(): float
    {
        if ($this->ratings->isEmpty()) {
            return 0.0;
        }

        $sum = 0;
        foreach ($this->ratings as $rating) {
            $sum += $rating->getNote();
        }

        return round($sum / $this->ratings->count(), 1);
    }

    public function addReservationEvenement(ReservationEvenement $reservationEvenement): self
    {
        if (!$this->getReservationEvenements()->contains($reservationEvenement)) {
            $this->getReservationEvenements()->add($reservationEvenement);
        }
        return $this;
    }

    public function removeReservationEvenement(ReservationEvenement $reservationEvenement): self
    {
        $this->getReservationEvenements()->removeElement($reservationEvenement);
        return $this;
    }

    public function getPlacesRestantes(): int
    {
        $nbReservationsExistantes = 0;
        foreach ($this->reservationEvenements as $res) {
            $statut = $res->getStatut_res();
            if ($statut !== \App\Enum\StatutReservationEvenement::ANNULEE && $statut !== \App\Enum\StatutReservationEvenement::LISTE_ATTENTE) {
                $nbReservationsExistantes += $res->getNb_billets();
            }
        }
        return max(0, $this->nb_places - $nbReservationsExistantes);
    }

}
