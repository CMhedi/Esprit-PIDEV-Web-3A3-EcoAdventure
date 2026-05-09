<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\StatutReclamation;
use App\Repository\ReclamationRepository;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamation')]
class Reclamation
{
    public function __construct()
    {
        $this->date_creation = new \DateTime();
    }
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id_reclamation = null;

    public function getId_reclamation(): ?int
    {
        return $this->id_reclamation;
    }

    public function setId_reclamation(int $id_reclamation): self
    {
        $this->id_reclamation = $id_reclamation;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 80)]
    private string $type = '';

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 20)]
    private string $priorite = 'BASSE'; // Valeurs: HAUTE, MOYENNE, BASSE

    public function getPriorite(): string
    {
        return $this->priorite;
    }
    public function setPriorite(string $priorite): self
    {
        $this->priorite = $priorite;
        return $this;
    }
    #[ORM\Column(type: 'string', length: 2000)]
    private string $contenu = '';

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }



    #[ORM\Column(type: 'string', length: 255)]
    private string $statut = StatutReclamation::EN_ATTENTE->value;

    public function getStatut(): StatutReclamation
    {
        return StatutReclamation::from($this->statut);
    }

    public function setStatut(StatutReclamation $statut): self
    {
        $this->statut = $statut->value;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private \DateTimeInterface $date_creation;

    public function getDate_creation(): \DateTimeInterface
    {
        return $this->date_creation;
    }

    protected function setDate_creation(\DateTimeInterface $date_creation): self
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'reclamations')]
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reponse = null;

    public function getReponse(): ?string
    {
        return $this->reponse;
    }

    public function setReponse(?string $reponse): self
    {
        $this->reponse = $reponse;
        return $this;
    }

}
