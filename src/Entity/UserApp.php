<?php

namespace App\Entity;

use App\Repository\UserAppRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Disponibilite;
use App\Enum\RoleUser;
use App\Enum\Specialite;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: UserAppRepository::class)]
#[ORM\Table(name: 'user_app')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class UserApp implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_user = null;

    #[ORM\Column(type: 'string', length: 80)]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 80)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire")]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 120, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image_url = null;

    #[ORM\Column(enumType: RoleUser::class)]
    private ?RoleUser $role = null;

    #[ORM\Column(type: 'string', length: 255)]
   
    private ?string $mot_de_passe = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $last_seen = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $age = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $experience = null;

    #[ORM\Column(enumType: Specialite::class, nullable: true)]
    
    private ?Specialite $specialite = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio_certifs = null;

    #[ORM\Column(enumType: Disponibilite::class, nullable: true)]
    private ?Disponibilite $disponibilite = null;

    // RELATIONS
    #[ORM\OneToMany(targetEntity: Inscription::class, mappedBy: 'userApp')]
    private Collection $inscriptions;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'userApp')]
    private Collection $messages;

    #[ORM\OneToMany(targetEntity: Reclamation::class, mappedBy: 'userApp')]
    private Collection $reclamations;

    #[ORM\OneToMany(targetEntity: ReservationActivite::class, mappedBy: 'userApp')]
    private Collection $reservationActivites;

    #[ORM\OneToMany(targetEntity: ReservationEvenement::class, mappedBy: 'userApp')]
    private Collection $reservationEvenements;

    #[ORM\OneToMany(targetEntity: ReservationSeance::class, mappedBy: 'userApp')]
    private Collection $reservationSeances;

#[ORM\OneToMany(mappedBy: "coach", targetEntity: Seance::class)]
private Collection $seances;

#[Assert\Callback]
    public function validateCoachRequirements(ExecutionContextInterface $context): void
    {
        if ($this->role === \App\Enum\RoleUser::COACH) {
            // L'âge est obligatoire pour le Coach
            if (null === $this->age) {
                $context->buildViolation("L'âge est obligatoire pour un coach.")
                    ->atPath('age')
                    ->addViolation();
            }

            // L'expérience est obligatoire pour le Coach
            if (null === $this->experience || $this->experience === '') {
                $context->buildViolation("Le nombre d'années d'expérience est obligatoire.")
                    ->atPath('experience')
                    ->addViolation();
            }
            if (null === $this->specialite) {
            $context->buildViolation("Choisir une spécialité est obligatoire pour un coach.")
                ->atPath('specialite')
                ->addViolation();
        }

            if (null === $this->disponibilite) {
                $context->buildViolation("Choisir votre disponibilité est obligatoire pour un coach.")
                    ->atPath('disponibilite')
                    ->addViolation();
            }
            
            // Validation de cohérence (optionnel mais recommandé)
            if ($this->age && $this->experience && ($this->age - (int)$this->experience < 16)) {
                $context->buildViolation("L'expérience est incohérente avec votre âge.")
                    ->atPath('experience')
                    ->addViolation();
            }
        }
    }

    public function __construct()
    {
        $this->date_creation = new \DateTime();
        $this->inscriptions = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->reclamations = new ArrayCollection();
        $this->reservationActivites = new ArrayCollection();
        $this->reservationEvenements = new ArrayCollection();
        $this->reservationSeances = new ArrayCollection();
        $this->seances = new ArrayCollection();
    }

    // ========== SYMFONY SECURITY METHODS ==========

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = [];
        if ($this->role) {
            $roles[] = 'ROLE_' . strtoupper($this->role->value);
        }
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return (string) $this->mot_de_passe;
    }

    public function eraseCredentials(): void
    {
    }

    // ========== GETTERS & SETTERS ==========

    public function getId(): ?int
    {
        return $this->id_user;
    }

    public function getId_user(): ?int
    {
        return $this->id_user;
    }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = $prenom; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): self { $this->telephone = $telephone; return $this; }



    public function getRole(): ?RoleUser { return $this->role; }
    public function setRole(RoleUser $role): self { $this->role = $role; return $this; }

    public function getMot_de_passe(): ?string { return $this->mot_de_passe; }
    public function setMot_de_passe(string $mot_de_passe): self { $this->mot_de_passe = $mot_de_passe; return $this; }

    public function getDate_creation(): ?\DateTimeInterface { return $this->date_creation; }
    public function setDate_creation(\DateTimeInterface $date_creation): self { $this->date_creation = $date_creation; return $this; }

    public function getLast_seen(): ?\DateTimeInterface { return $this->last_seen; }
    public function setLast_seen(?\DateTimeInterface $last_seen): self { $this->last_seen = $last_seen; return $this; }

    public function getAge(): ?int { return $this->age; }
    public function setAge(?int $age): self { $this->age = $age; return $this; }

    public function getExperience(): ?string { return $this->experience; }
    public function setExperience(?string $experience): self { $this->experience = $experience; return $this; }

    public function getSpecialite(): ?Specialite { return $this->specialite; }
    public function setSpecialite(?Specialite $specialite): self { $this->specialite = $specialite; return $this; }

// Salla7 el asami mte3 el Getters/Setters hadhom bedhabt:

public function getBioCertifs(): ?string // Na7i el underscore houni
{ 
    return $this->bio_certifs; 
}

public function setBioCertifs(?string $bio_certifs): self 
{ 
    $this->bio_certifs = $bio_certifs; 
    return $this; 
}

public function getImageUrl(): ?string // Zeda houni khir
{ 
    return $this->image_url; 
}

public function setImageUrl(?string $image_url): self 
{ 
    $this->image_url = $image_url; 
    return $this; 
}

    public function getImage_url(): ?string
    {
        return $this->image_url;
    }

    public function setImage_url(?string $image_url): self
    {
        $this->image_url = $image_url;
        return $this;
    }

    public function getDisponibilite(): ?Disponibilite { return $this->disponibilite; }
    public function setDisponibilite(?Disponibilite $disponibilite): self { $this->disponibilite = $disponibilite; return $this; }

    // Collection Getters (Inscriptions, Messages, etc.)
    public function getInscriptions(): Collection { return $this->inscriptions; }
    public function addInscription(Inscription $inscription): self
    {
        if (!$this->inscriptions->contains($inscription)) {
            $this->inscriptions->add($inscription);
            $inscription->setUserApp($this);
        }

        return $this;
    }

    public function removeInscription(Inscription $inscription): self
    {
        if ($this->inscriptions->removeElement($inscription) && $inscription->getUserApp() === $this) {
            $inscription->setUserApp(null);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        $this->getMessages()->removeElement($message);
        return $this;
    }



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

    public function getMessages(): Collection { return $this->messages; }



 
}
