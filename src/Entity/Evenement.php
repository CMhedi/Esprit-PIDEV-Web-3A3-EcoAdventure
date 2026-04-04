<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\CategorieEvenement;
use App\Repository\EvenementRepository;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_evenement = null;

    public function getId_evenement(): ?int
    {
        return $this->id_evenement;
    }

    public function setId_evenement(int $id_evenement): self
    {
        $this->id_evenement = $id_evenement;
        return $this;
    }

#[ORM\Column(type: 'string', length: 150)]
private ?string $titre = null;

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

#[ORM\Column(type: 'string', length: 1000, nullable: true)]
private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }



#[ORM\Column(enumType: CategorieEvenement::class)]
private ?CategorieEvenement $categorie_evt = null;

    public function getCategorie_evt(): ?CategorieEvenement
    {
        return $this->categorie_evt;
    }

    public function setCategorie_evt(CategorieEvenement $categorie_evt): self
    {
        $this->categorie_evt = $categorie_evt;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_event = null;

    public function getDate_event(): ?\DateTimeInterface
    {
        return $this->date_event;
    }

    public function setDate_event(\DateTimeInterface $date_event): self
    {
        $this->date_event = $date_event;
        return $this;
    }

#[ORM\Column(type: 'string', length: 150)]
private ?string $lieu = null;

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $nb_places = null;

    public function getNb_places(): ?int
    {
        return $this->nb_places;
    }

    public function setNb_places(int $nb_places): self
    {
        $this->nb_places = $nb_places;
        return $this;
    }

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

    #[ORM\OneToMany(targetEntity: ReservationEvenement::class, mappedBy: 'evenement')]
    private Collection $reservationEvenements;

    /**
     * @return Collection<int, ReservationEvenement>
     */
    public function getReservationEvenements(): Collection
    {
        if (!$this->reservationEvenements instanceof Collection) {
            $this->reservationEvenements = new ArrayCollection();
        }
        return $this->reservationEvenements;
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

}
