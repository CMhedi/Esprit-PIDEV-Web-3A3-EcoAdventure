<?php

namespace App\Entity;

use App\Repository\LocalisationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalisationRepository::class)]
class Localisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 🔵 Gouvernorat (ex: Sfax, Tunis...)
    #[ORM\Column(length: 100)]
    private ?string $gouvernorat = null;

    // 🔵 Délégation (ex: Sakiet Ezzit, La Marsa...)
    #[ORM\Column(length: 100)]
    private ?string $delegation = null;

    // 🔵 Ville (optionnel mais utile)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    // 🔵 Latitude GPS
    #[ORM\Column(type: "float")]
    private ?float $latitude = null;

    // 🔵 Longitude GPS
    #[ORM\Column(type: "float")]
    private ?float $longitude = null;

    // =========================
    // GETTERS / SETTERS
    // =========================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGouvernorat(): ?string
    {
        return $this->gouvernorat;
    }

    public function setGouvernorat(string $gouvernorat): self
    {
        $this->gouvernorat = $gouvernorat;
        return $this;
    }

    public function getDelegation(): ?string
    {
        return $this->delegation;
    }

    public function setDelegation(string $delegation): self
    {
        $this->delegation = $delegation;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }
}