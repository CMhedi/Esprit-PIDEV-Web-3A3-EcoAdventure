<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
class Conversation
{
    private const PRIVATE_BLOCK_PREFIX = '[PRIVATE_BLOCKED] ';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_conversation = null;

    #[ORM\ManyToOne(targetEntity: UserApp::class)]
    #[ORM\JoinColumn(name: 'id_createur', referencedColumnName: 'id_user', nullable: false)]
    #[Assert\NotNull(message: "Le créateur de la conversation est obligatoire.")]
    private ?UserApp $createur = null;

    #[ORM\ManyToMany(targetEntity: UserApp::class)]
    #[ORM\JoinTable(
        name: 'conversation_user',
        joinColumns: [new ORM\JoinColumn(name: 'id_conversation', referencedColumnName: 'id_conversation')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user')]
    )]
    #[Assert\Count(min: 1, minMessage: "Il doit y avoir au moins un participant.")]
    private Collection $participants;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank(message: "Le titre ne peut pas être vide.")]
    #[Assert\Length(
        min: 2,
        max: 150,
        minMessage: "Le titre doit comporter au moins {{ limit }} caractères.",
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $titre = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    #[Assert\NotNull(message: "Veuillez préciser s'il s'agit d'un groupe ou non.")]
    private ?bool $est_groupe = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: "La date de création est requise.")]
    #[Assert\LessThanOrEqual("now", message: "La date de création ne peut pas être dans le futur.")]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', cascade: ['remove'])]
    private Collection $messages;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->date_creation = new \DateTime();
    }

    public function getId_conversation(): ?int
    {
        return $this->id_conversation;
    }

    public function setId_conversation(int $id_conversation): self
    {
        $this->id_conversation = $id_conversation;
        return $this;
    }

    public function getCreateur(): ?UserApp
    {
        return $this->createur;
    }

    public function setCreateur(?UserApp $createur): self
    {
        $this->createur = $createur;
        return $this;
    }

    /**
     * @return Collection<int, UserApp>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(UserApp $user): self
    {
        if (!$this->participants->contains($user)) {
            $this->participants->add($user);
        }
        return $this;
    }

    public function removeParticipant(UserApp $user): self
    {
        $this->participants->removeElement($user);
        return $this;
    }

    public function getTitre(): ?string
    {
        if ($this->titre === null) {
            return null;
        }

        if (str_starts_with($this->titre, self::PRIVATE_BLOCK_PREFIX)) {
            return substr($this->titre, strlen(self::PRIVATE_BLOCK_PREFIX));
        }

        return $this->titre;
    }

    public function setTitre(?string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function isPrivateBlocked(): bool
    {
        return !$this->isEst_groupe() && $this->titre !== null && str_starts_with($this->titre, self::PRIVATE_BLOCK_PREFIX);
    }

    public function blockPrivateConversation(): self
    {
        if (!$this->isEst_groupe() && !$this->isPrivateBlocked()) {
            $this->titre = self::PRIVATE_BLOCK_PREFIX . ($this->titre ?? '');
        }

        return $this;
    }

    public function isEst_groupe(): ?bool
    {
        return $this->est_groupe;
    }

    public function setEst_groupe(bool $est_groupe): self
    {
        $this->est_groupe = $est_groupe;
        return $this;
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

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getConversation() === $this) {
                $message->setConversation(null);
            }
        }
        return $this;
    }
}
