<?php

namespace App\Entity;
use App\Entity\UserApp;
use App\Repository\InscriptionRepository;
use App\Enum\StatutInscription;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
#[ORM\Table(name: 'inscription')]
class Inscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_inscription', type: 'integer')]
    private ?int $id_inscription = null;

    #[ORM\Column(name: 'date_inscription', type: 'datetime')]
    private ?\DateTimeInterface $date_inscription = null;

    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[ORM\Column(name: 'statut_inscr', type: 'string', enumType: StatutInscription::class, length: 255)]
    private ?StatutInscription $statut_inscr = null;

    #[ORM\Column(name: 'montant_total', type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant_total = null;

    #[Assert\NotBlank(message: 'Le nom utilisateur est obligatoire.')]
    #[Assert\Length(min: 3, max: 255)]
    #[ORM\Column(name: 'nom_user', type: 'string', length: 255, nullable: true)]
    private ?string $nom_user = null;

    #[ORM\Column(name: 'nom_pack', type: 'string', length: 255, nullable: true)]
    private ?string $nom_pack = null;

    #[Assert\NotNull(message: 'Le pack est obligatoire.')]
    #[ORM\ManyToOne(targetEntity: Pack::class)]
    #[ORM\JoinColumn(name: 'id_pack', referencedColumnName: 'id_pack', nullable: false)]
    private ?Pack $pack = null;

    #[Assert\NotNull(message: 'L utilisateur est obligatoire.')]
    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private ?UserApp $userApp = null;

    public function getIdInscription(): ?int
    {
        return $this->id_inscription;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->date_inscription;
    }
    

    public function setDateInscription(\DateTimeInterface $date_inscription): self
    {
        $this->date_inscription = $date_inscription;
        return $this;
    }

    public function getStatutInscr(): ?StatutInscription
    {
        return $this->statut_inscr;
    }

    public function setStatutInscr(StatutInscription $statut_inscr): self
    {
        $this->statut_inscr = $statut_inscr;
        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montant_total;
    }

    public function setMontantTotal(string $montant_total): self
    {
        $this->montant_total = $montant_total;
        return $this;
    }

    public function getNomUser(): ?string
    {
        return $this->nom_user;
    }

    public function setNomUser(?string $nom_user): self
    {
        $this->nom_user = $nom_user;
        return $this;
    }

    public function getNomPack(): ?string
    {
        return $this->nom_pack;
    }

    public function setNomPack(?string $nom_pack): self
    {
        $this->nom_pack = $nom_pack;
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
