<?php

namespace App\Entity;

use App\Enum\StatutMessage;
use App\Enum\TypeMessage;
use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_message = null;

    #[ORM\Column(enumType: TypeMessage::class)]
    #[Assert\NotNull(message: "Le type de message est obligatoire.")]
    private ?TypeMessage $type_message = null;

    #[ORM\Column(type: 'string', length: 10000, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: "Le contenu ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Assert\NotBlank(message: "Le contenu du message ne peut pas être vide.", groups: ["text_message"])]
    private ?string $contenu = null;

    #[ORM\Column(enumType: StatutMessage::class)]
    #[Assert\NotNull(message: "Le statut du message est obligatoire.")]
    private ?StatutMessage $statut_message = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: "La date d'envoi est obligatoire.")]
    #[Assert\LessThanOrEqual("now", message: "La date d'envoi ne peut pas être dans le futur.")]
    private ?\DateTimeInterface $date_envoi = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_lecture = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\Expression(
        "this.getDate_modifier() == null || this.getDate_modifier() >= this.getDate_envoi()",
        message: "La date de modification ne peut pas être antérieure à la date d'envoi."
    )]
    private ?\DateTimeInterface $date_modifier = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $reactions = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attachments = [];

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'id_conversation', referencedColumnName: 'id_conversation', nullable: false)]
    #[Assert\NotNull(message: "Le message doit être rattaché à une conversation.")]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    #[Assert\NotNull(message: "L'auteur du message est obligatoire.")]
    private ?UserApp $userApp = null;

    public function __construct()
    {
        $this->date_envoi = new \DateTime();
        // Par défaut, un nouveau message est souvent mis en statut 'ENVOYE' ou 'NON_LU'
    }

    public function getId_message(): ?int
    {
        return $this->id_message;
    }

    public function setId_message(int $id_message): self
    {
        $this->id_message = $id_message;
        return $this;
    }

    public function getType_message(): ?TypeMessage
    {
        return $this->type_message;
    }

    public function setType_message(TypeMessage $type_message): self
    {
        $this->type_message = $type_message;
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getStatut_message(): ?StatutMessage
    {
        return $this->statut_message;
    }

    public function setStatut_message(StatutMessage $statut_message): self
    {
        $this->statut_message = $statut_message;
        return $this;
    }

    public function getDate_envoi(): ?\DateTimeInterface
    {
        return $this->date_envoi;
    }

    public function setDate_envoi(\DateTimeInterface $date_envoi): self
    {
        $this->date_envoi = $date_envoi;
        return $this;
    }

    public function getDate_lecture(): ?\DateTimeInterface
    {
        return $this->date_lecture;
    }

    public function setDate_lecture(?\DateTimeInterface $date_lecture): self
    {
        $this->date_lecture = $date_lecture;
        return $this;
    }

    public function getDate_modifier(): ?\DateTimeInterface
    {
        return $this->date_modifier;
    }

    public function setDate_modifier(?\DateTimeInterface $date_modifier): self
    {
        $this->date_modifier = $date_modifier;
        return $this;
    }

    public function getReactions(): array
    {
        return $this->reactions ?? [];
    }

    public function setReactions(?array $reactions): self
    {
        $this->reactions = $reactions ?? [];
        return $this;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getAttachments(): array
    {
        return $this->attachments ?? [];
    }

    public function setAttachments(?array $attachments): self
    {
        $this->attachments = $attachments ?? [];
        return $this;
    }

    public function getUserApp(): ?UserApp
    {
        return $this->userApp;
    }

    public function setUserApp(?UserApp $userApp): self
    {
        $this->userApp = $userApp;
        return $this;
    }
}
