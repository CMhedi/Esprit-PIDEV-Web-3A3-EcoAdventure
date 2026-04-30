<?php

namespace App\Entity;

use App\Repository\UserAppRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Disponibilite;
use App\Enum\RoleUser;
use App\Enum\Specialite;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
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

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $referralCode = null;

    #[ORM\Column(type: 'integer', options: ["default" => 0])]
    private int $loyaltyPoints = 0;

    // Google OAuth fields
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $google_token = null;

    // Churn prediction fields
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $churnProbability = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $churnPrediction = false;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'low'])]
    private string $churnRisk = 'low';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastPredictionAt = null;

    // Physical attributes fields
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $weight = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $height = null;

    #[ORM\Column(type: 'string', length: 1, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $activityLevel = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $goalNotified = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $failedAttempts = 0;

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

    #[ORM\ManyToMany(targetEntity: Conversation::class, mappedBy: 'participants')]
    private Collection $conversations;

    /**
     * @var list<float>|null
     */
    private ?array $faceDescriptor = null;

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
        $this->conversations = new ArrayCollection();
        $this->referralCode = strtoupper(substr(uniqid(), -6));
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

    // ========== VALIDATION ==========

    #[Assert\Callback]
    public function validateCoachRequirements(ExecutionContextInterface $context): void
    {
        if ($this->role === RoleUser::COACH) {
            if (null === $this->age) {
                $context->buildViolation("L'âge est obligatoire pour un coach.")
                    ->atPath('age')
                    ->addViolation();
            }

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

            if ($this->age && $this->experience && ($this->age - (int) $this->experience < 16)) {
                $context->buildViolation("L'expérience est incohérente avec votre âge.")
                    ->atPath('experience')
                    ->addViolation();
            }
        }
    }

    // ========== GETTERS & SETTERS ==========

    public function getId(): ?int { return $this->id_user; }
    public function getId_user(): ?int { return $this->id_user; }
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
    public function getBioCertifs(): ?string { return $this->bio_certifs; }
    public function setBioCertifs(?string $bio_certifs): self { $this->bio_certifs = $bio_certifs; return $this; }
    public function getBio_certifs(): ?string { return $this->bio_certifs; }
    public function setBio_certifs(?string $bio_certifs): self { $this->bio_certifs = $bio_certifs; return $this; }
    public function getImageUrl(): ?string { return $this->image_url; }
    public function setImageUrl(?string $image_url): self { $this->image_url = $image_url; return $this; }
    public function getImage_url(): ?string { return $this->image_url; }
    public function setImage_url(?string $image_url): self { $this->image_url = $image_url; return $this; }
    public function getDisponibilite(): ?Disponibilite { return $this->disponibilite; }
    public function setDisponibilite(?Disponibilite $disponibilite): self { $this->disponibilite = $disponibilite; return $this; }
    public function getReferralCode(): ?string { return $this->referralCode; }
    public function setReferralCode(?string $code): self { $this->referralCode = $code; return $this; }
    public function getLoyaltyPoints(): int { return $this->loyaltyPoints; }
    public function setLoyaltyPoints(int $points): self { $this->loyaltyPoints = $points; return $this; }
    public function addLoyaltyPoints(int $points): self { $this->loyaltyPoints += $points; return $this; }

    // Google token
    public function getGoogleToken(): ?string { return $this->google_token; }
    public function setGoogleToken(?string $google_token): self { $this->google_token = $google_token; return $this; }
    /** @return list<float>|null */
    public function getFaceDescriptor(): ?array { return $this->faceDescriptor; }
    /** @param list<float>|null $faceDescriptor */
    public function setFaceDescriptor(?array $faceDescriptor): self { $this->faceDescriptor = $faceDescriptor; return $this; }

    // Churn prediction
    public function getChurnProbability(): ?float { return $this->churnProbability; }
    public function setChurnProbability(float $value): self { $this->churnProbability = $value; return $this; }
    public function getChurnPrediction(): bool { return $this->churnPrediction; }
    public function setChurnPrediction(bool $value): self { $this->churnPrediction = $value; return $this; }
    public function getChurnRisk(): string { return $this->churnRisk; }
    public function setChurnRisk(string $value): self { $this->churnRisk = $value; return $this; }
    public function getLastPredictionAt(): ?\DateTimeInterface { return $this->lastPredictionAt; }
    public function setLastPredictionAt(\DateTimeInterface $value): self { $this->lastPredictionAt = $value; return $this; }

    // Physical attributes
    public function getWeight(): ?float { return $this->weight; }
    public function setWeight(float $v): self { $this->weight = $v; return $this; }
    public function getHeight(): ?float { return $this->height; }
    public function setHeight(float $v): self { $this->height = $v; return $this; }
    public function getGender(): ?string { return $this->gender; }
    public function setGender(string $v): self { $this->gender = $v; return $this; }
    public function getActivityLevel(): ?float { return $this->activityLevel; }
    public function setActivityLevel(float $v): self { $this->activityLevel = $v; return $this; }

    // Goal notification
    public function isGoalNotified(): bool { return $this->goalNotified; }
    public function setGoalNotified(bool $goalNotified): self { $this->goalNotified = $goalNotified; return $this; }

    // Security failed login attempts
    public function getFailedAttempts(): int { return $this->failedAttempts; }
    public function setFailedAttempts(int $failedAttempts): self { $this->failedAttempts = max(0, $failedAttempts); return $this; }
    public function incrementFailedAttempts(): self { $this->failedAttempts++; return $this; }
    public function resetFailedAttempts(): self { $this->failedAttempts = 0; return $this; }

    // Relations
    public function getInscriptions(): Collection { return $this->inscriptions; }
    public function getMessages(): Collection { return $this->messages; }
    public function getReclamations(): Collection { return $this->reclamations; }
    public function getReservationActivites(): Collection { return $this->reservationActivites; }
    public function getReservationEvenements(): Collection { return $this->reservationEvenements; }
    public function getReservationSeances(): Collection { return $this->reservationSeances; }
    public function getSeances(): Collection { return $this->seances; }
    public function getConversations(): Collection { return $this->conversations; }

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
}
