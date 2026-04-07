<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Enum\StatutInscription;
use App\Repository\InscriptionRepository;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
#[ORM\Table(name: 'inscription')]
class Inscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_inscription = null;

    public function getId_inscription(): ?int
    {
        return $this->id_inscription;
    }

    public function setId_inscription(int $id_inscription): self
    {
        $this->id_inscription = $id_inscription;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_inscription = null;

    public function getDate_inscription(): ?\DateTimeInterface
    {
        return $this->date_inscription;
    }

    public function setDate_inscription(\DateTimeInterface $date_inscription): self
    {
        $this->date_inscription = $date_inscription;
        return $this;
    }



#[ORM\Column(enumType: StatutInscription::class)]
private ?StatutInscription $statut_inscr = null;

    public function getStatut_inscr(): ?StatutInscription
    {
        return $this->statut_inscr;
    }

    public function setStatut_inscr(StatutInscription $statut_inscr): self
    {
        $this->statut_inscr = $statut_inscr;
        return $this;
    }

#[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
private ?string $montant_total = null;

    public function getMontant_total(): ?float
    {
        return $this->montant_total;
    }

    public function setMontant_total(float $montant_total): self
    {
        $this->montant_total = $montant_total;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'inscriptions')]
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

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(name: 'id_pack', referencedColumnName: 'id_pack')]
    private ?Pack $pack = null;

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self
    {
        $this->pack = $pack;
        return $this;
    }

#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $nom_user = null;

    public function getNom_user(): ?string
    {
        return $this->nom_user;
    }

    public function setNom_user(?string $nom_user): self
    {
        $this->nom_user = $nom_user;
        return $this;
    }

#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $nom_pack = null;

    public function getNom_pack(): ?string
    {
        return $this->nom_pack;
    }

    public function setNom_pack(?string $nom_pack): self
    {
        $this->nom_pack = $nom_pack;
        return $this;
    }

}
