<?php

namespace App\Entity;

use App\Enum\StatutReservationActivite;
use App\Repository\ReservationActiviteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationActiviteRepository::class)]
#[ORM\Table(name: 'reservation_activite')]
class ReservationActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_res_act', type: 'integer')]
    private ?int $id_res_act = null;

    #[ORM\Column(name: 'date_reservation', type: 'datetime')]
    #[Assert\NotNull(message: 'La date de reservation est obligatoire.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date de reservation doit etre aujourd hui ou dans le futur.')]
    private ?\DateTimeInterface $dateRes = null;

    #[ORM\Column(enumType: StatutReservationActivite::class)]
    #[Assert\NotNull(message: 'Le statut de reservation est obligatoire.')]
    private ?StatutReservationActivite $statut_res = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull(message: 'Le nombre de personnes est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre de personnes doit etre positif.')]
    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: 'Le nombre de personnes doit etre entre {{ min }} et {{ max }}.'
    )]
    private ?int $nb_personnes = null;

    #[ORM\ManyToOne(targetEntity: UserApp::class, inversedBy: 'reservationActivites')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private ?UserApp $userApp = null;

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'reservationActivites')]
    #[ORM\JoinColumn(name: 'id_activite', referencedColumnName: 'id_activite', nullable: false)]
    private ?Activite $activite = null;

    public function getIdResAct(): ?int
    {
        return $this->id_res_act;
    }

    public function getDateRes(): ?\DateTimeInterface
    {
        return $this->dateRes;
    }

    public function setDateRes(?\DateTimeInterface $dateRes): self
    {
        $this->dateRes = $dateRes;
        return $this;
    }

    public function getStatutRes(): ?StatutReservationActivite
    {
        return $this->statut_res;
    }

    public function setStatutRes(?StatutReservationActivite $statut_res): self
    {
        $this->statut_res = $statut_res;
        return $this;
    }

    public function getNbPersonnes(): ?int
    {
        return $this->nb_personnes;
    }

    public function setNbPersonnes(?int $nb_personnes): self
    {
        $this->nb_personnes = $nb_personnes;
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

    public function getActivite(): ?Activite
    {
        return $this->activite;
    }

    public function setActivite(?Activite $activite): self
    {
        $this->activite = $activite;
        return $this;
    }
}
