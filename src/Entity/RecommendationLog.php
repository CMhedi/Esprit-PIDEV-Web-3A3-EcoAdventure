<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\UserApp;
use App\Repository\RecommendationLogRepository;

#[ORM\Entity(repositoryClass: RecommendationLogRepository::class)]
#[ORM\Table(name: 'recommendation_log')]
class RecommendationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $rec_id = null;

    public function getRec_id(): ?int
    {
        return $this->rec_id;
    }

    public function setRec_id(int $rec_id): self
    {
        $this->rec_id = $rec_id;
        return $this;
    }



#[ORM\ManyToOne(targetEntity: UserApp::class)]
#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
private ?UserApp $user = null;

public function getUser(): ?UserApp
{
    return $this->user;
}

public function setUser(?UserApp $user): self
{
    $this->user = $user;
    return $this;
}

public function __construct()
{
    $this->created_at = new \DateTime();
}

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $request_json = null;

    public function getRequest_json(): ?string
    {
        return $this->request_json;
    }

    public function setRequest_json(string $request_json): self
    {
        $this->request_json = $request_json;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $results_json = null;

    public function getResults_json(): ?string
    {
        return $this->results_json;
    }

    public function setResults_json(string $results_json): self
    {
        $this->results_json = $results_json;
        return $this;
    }

}
