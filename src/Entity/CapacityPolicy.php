<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\CapacityPolicyRepository;

#[ORM\Entity(repositoryClass: CapacityPolicyRepository::class)]
#[ORM\Table(name: 'capacity_policy')]
class CapacityPolicy
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    private ?string $categorie_act = null;

    #[ORM\Column(type: 'integer')]
    private ?int $capacite_totale = null;

    public function getCategorieAct(): ?string
    {
        return $this->categorie_act;
    }

    public function setCategorieAct(string $categorie_act): self
    {
        $this->categorie_act = $categorie_act;
        return $this;
    }

    public function getCapaciteTotale(): ?int
    {
        return $this->capacite_totale;
    }

    public function setCapaciteTotale(int $capacite_totale): self
    {
        $this->capacite_totale = $capacite_totale;
        return $this;
    }
}