<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\FeedbackEventRepository;
use App\Entity\UserApp;
use App\Entity\Pack;

#[ORM\Entity(repositoryClass: FeedbackEventRepository::class)]
#[ORM\Table(name: 'feedback_event')]
class FeedbackEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: UserApp::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private ?UserApp $user = null;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    #[ORM\JoinColumn(name: 'pack_id', referencedColumnName: 'id_pack', nullable: false)]
    private ?Pack $pack = null;

    #[ORM\Column(type: 'string', length: 40)]
    private ?string $action = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $meta_json = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUser(): ?UserApp
    {
        return $this->user;
    }

    public function setUser(?UserApp $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self
    {
        $this->pack = $pack;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getMetaJson(): ?string
    {
        return $this->meta_json;
    }

    public function setMetaJson(?string $meta_json): self
    {
        $this->meta_json = $meta_json;
        return $this;
    }
}