<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Entity\UserApp;
use App\Repository\ConversationRepository;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_conversation = null;

    #[ORM\ManyToOne(targetEntity: UserApp::class)]
    #[ORM\JoinColumn(name: 'id_createur', referencedColumnName: 'id_user', nullable: false)]
    private ?UserApp $createur = null;

    #[ORM\ManyToMany(targetEntity: UserApp::class)]
    #[ORM\JoinTable(
        name: 'conversation_user',
        joinColumns: [new ORM\JoinColumn(name: 'conversation_id_conversation', referencedColumnName: 'id_conversation')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'user_app_id_user', referencedColumnName: 'id_user')]
    )]
    private Collection $participants;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $est_groupe = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation')]
    private Collection $messages;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
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

    public function setCreateur(UserApp $createur): self
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
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
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
        }
        return $this;
    }

    public function removeMessage(Message $message): self
    {
        $this->messages->removeElement($message);
        return $this;
    }
}
