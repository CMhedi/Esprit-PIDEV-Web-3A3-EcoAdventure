<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\TypeMessage;
use App\Repository\MessageRepository;
use App\Enum\StatutMessage;
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_message = null;

    public function getId_message(): ?int
    {
        return $this->id_message;
    }

    public function setId_message(int $id_message): self
    {
        $this->id_message = $id_message;
        return $this;
    }



#[ORM\Column(enumType: TypeMessage::class)]
private ?TypeMessage $type_message = null;

    public function getType_message(): ?TypeMessage
    {
        return $this->type_message;
    }

    public function setType_message(TypeMessage $type_message): self
    {
        $this->type_message = $type_message;
        return $this;
    }

#[ORM\Column(type: 'string', length: 2000, nullable: true)]
private ?string $contenu = null;

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }



#[ORM\Column(enumType: StatutMessage::class)]
private ?StatutMessage $statut_message = null;

    public function getStatut_message(): ?StatutMessage
    {
        return $this->statut_message;
    }

    public function setStatut_message(StatutMessage $statut_message): self
    {
        $this->statut_message = $statut_message;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_envoi = null;

    public function getDate_envoi(): ?\DateTimeInterface
    {
        return $this->date_envoi;
    }

    public function setDate_envoi(\DateTimeInterface $date_envoi): self
    {
        $this->date_envoi = $date_envoi;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_lecture = null;

    public function getDate_lecture(): ?\DateTimeInterface
    {
        return $this->date_lecture;
    }

    public function setDate_lecture(?\DateTimeInterface $date_lecture): self
    {
        $this->date_lecture = $date_lecture;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_modifier = null;

    public function getDate_modifier(): ?\DateTimeInterface
    {
        return $this->date_modifier;
    }

    public function setDate_modifier(?\DateTimeInterface $date_modifier): self
    {
        $this->date_modifier = $date_modifier;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'id_conversation', referencedColumnName: 'id_conversation')]
    private ?Conversation $conversation = null;

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user')]
    private ?UserApp $userApp = null;

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
