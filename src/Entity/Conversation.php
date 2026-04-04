<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ConversationRepository;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_conversation = null;

    public function getId_conversation(): ?int
    {
        return $this->id_conversation;
    }

    public function setId_conversation(int $id_conversation): self
    {
        $this->id_conversation = $id_conversation;
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

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $est_groupe = null;

    public function isEst_groupe(): ?bool
    {
        return $this->est_groupe;
    }

    public function setEst_groupe(bool $est_groupe): self
    {
        $this->est_groupe = $est_groupe;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_creation = null;

    public function getDate_creation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDate_creation(\DateTimeInterface $date_creation): self
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation')]
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

}
