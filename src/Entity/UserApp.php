<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\Disponibilite;
use App\Enum\RoleUser;
use App\Enum\Specialite;

use App\Repository\UserAppRepository;

#[ORM\Entity(repositoryClass: UserAppRepository::class)]
#[ORM\Table(name: 'user_app')]
class UserApp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_user = null;

    public function getId_user(): ?int
    {
        return $this->id_user;
    }

    public function setId_user(int $id_user): self
    {
        $this->id_user = $id_user;
        return $this;
    }

#[ORM\Column(type: 'string', length: 80)]
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

#[ORM\Column(type: 'string', length: 80)]
private ?string $prenom = null;

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }
#[ORM\Column(type: 'string', length: 120)]
private ?string $email = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }


#[ORM\Column(type: 'string', length: 30, nullable: true)]
private ?string $telephone = null;


    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
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

   #[ORM\Column(enumType: RoleUser::class)]
    private ?RoleUser $role = null;

    public function getRole(): ?RoleUser
    {
        return $this->role;
    }

    public function setRole(RoleUser $role): self
    {
        $this->role = $role;
        return $this;
    }

#[ORM\Column(type: 'string', length: 255)]
private ?string $mot_de_passe = null;

    public function getMot_de_passe(): ?string
    {
        return $this->mot_de_passe;
    }

    public function setMot_de_passe(string $mot_de_passe): self
    {
        $this->mot_de_passe = $mot_de_passe;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $referralCode = null;

    #[ORM\Column(type: 'integer', options: ["default" => 0])]
    private int $loyaltyPoints = 0;

    public function getReferralCode(): ?string { return $this->referralCode; }
    public function setReferralCode(?string $code): self { $this->referralCode = $code; return $this; }
    public function getLoyaltyPoints(): int { return $this->loyaltyPoints; }
    public function setLoyaltyPoints(int $points): self { $this->loyaltyPoints = $points; return $this; }
    public function addLoyaltyPoints(int $points): self { $this->loyaltyPoints += $points; return $this; }

    public function __construct()
    {
        $this->date_creation = new \DateTime();
        $this->referralCode = strtoupper(substr(uniqid(), -6));
    }

    public function getDate_creation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDate_creation(\DateTimeInterface $date_creation): self
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $age = null;

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;
        return $this;
    }

   #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $experience = null;

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(?string $experience): self
    {
        $this->experience = $experience;
        return $this;
    }

#[ORM\Column(enumType: Specialite::class, nullable: true)]
private ?Specialite $specialite = null;

    public function getSpecialite(): ?Specialite
    {
        return $this->specialite;
    }

    public function setSpecialite(?Specialite $specialite): self
    {
        $this->specialite = $specialite;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio_certifs = null;

    public function getBio_certifs(): ?string
    {
        return $this->bio_certifs;
    }

    public function setBio_certifs(?string $bio_certifs): self
    {
        $this->bio_certifs = $bio_certifs;
        return $this;
    }

#[ORM\Column(enumType: Disponibilite::class, nullable: true)]
private ?Disponibilite $disponibilite = null;

    public function getDisponibilite(): ?Disponibilite
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?Disponibilite $disponibilite): self
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Inscription::class, mappedBy: 'userApp')]
    private Collection $inscriptions;

    /**
     * @return Collection<int, Inscription>
     */
    public function getInscriptions(): Collection
    {
        if (!$this->inscriptions instanceof Collection) {
            $this->inscriptions = new ArrayCollection();
        }
        return $this->inscriptions;
    }

    public function addInscription(Inscription $inscription): self
    {
        if (!$this->getInscriptions()->contains($inscription)) {
            $this->getInscriptions()->add($inscription);
        }
        return $this;
    }

    public function removeInscription(Inscription $inscription): self
    {
        $this->getInscriptions()->removeElement($inscription);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'userApp')]
    private Collection $messages;

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        if (!$this->messages instanceof Collection) {
            $this->messages = new ArrayCollection();
        }
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->getMessages()->contains($message)) {
            $this->getMessages()->add($message);
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        $this->getMessages()->removeElement($message);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Reclamation::class, mappedBy: 'userApp')]
    private Collection $reclamations;

    /**
     * @return Collection<int, Reclamation>
     */
    public function getReclamations(): Collection
    {
        if (!$this->reclamations instanceof Collection) {
            $this->reclamations = new ArrayCollection();
        }
        return $this->reclamations;
    }

    public function addReclamation(Reclamation $reclamation): self
    {
        if (!$this->getReclamations()->contains($reclamation)) {
            $this->getReclamations()->add($reclamation);
        }
        return $this;
    }

    public function removeReclamation(Reclamation $reclamation): self
    {
        $this->getReclamations()->removeElement($reclamation);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ReservationActivite::class, mappedBy: 'userApp')]
    private Collection $reservationActivites;

    /**
     * @return Collection<int, ReservationActivite>
     */
    public function getReservationActivites(): Collection
    {
        if (!$this->reservationActivites instanceof Collection) {
            $this->reservationActivites = new ArrayCollection();
        }
        return $this->reservationActivites;
    }

    public function addReservationActivite(ReservationActivite $reservationActivite): self
    {
        if (!$this->getReservationActivites()->contains($reservationActivite)) {
            $this->getReservationActivites()->add($reservationActivite);
        }
        return $this;
    }

    public function removeReservationActivite(ReservationActivite $reservationActivite): self
    {
        $this->getReservationActivites()->removeElement($reservationActivite);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: ReservationEvenement::class, mappedBy: 'userApp')]
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

    #[ORM\OneToMany(targetEntity: ReservationSeance::class, mappedBy: 'userApp')]
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

    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'userApp')]
    private Collection $seances;

    /**
     * @return Collection<int, Seance>
     */
    public function getSeances(): Collection
    {
        if (!$this->seances instanceof Collection) {
            $this->seances = new ArrayCollection();
        }
        return $this->seances;
    }

    public function addSeance(Seance $seance): self
    {
        if (!$this->getSeances()->contains($seance)) {
            $this->getSeances()->add($seance);
        }
        return $this;
    }

    public function removeSeance(Seance $seance): self
    {
        $this->getSeances()->removeElement($seance);
        return $this;
    }

}
