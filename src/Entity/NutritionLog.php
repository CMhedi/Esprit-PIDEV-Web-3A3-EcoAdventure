<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\NutritionLogRepository;
use App\Entity\UserApp;
#[ORM\Entity(repositoryClass: NutritionLogRepository::class)]
#[ORM\Table(name: 'nutrition_log')]
class NutritionLog
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: 'integer')]
private ?int $id = null;

#[ORM\ManyToOne(targetEntity: UserApp::class)]
#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: true)]
private ?UserApp $user = null;

// ===== ID =====
public function getId(): ?int
{
    return $this->id;
}

public function setId(int $id): self
{
    $this->id = $id;
    return $this;
}

// ===== RELATION USER =====
public function getUser(): ?UserApp
{
    return $this->user;
}

public function setUser(?UserApp $user): self
{
    $this->user = $user;
    return $this;
}

#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $food_name = null;

    public function getFood_name(): ?string
    {
        return $this->food_name;
    }

    public function setFood_name(?string $food_name): self
    {
        $this->food_name = $food_name;
        return $this;
    }

#[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $calories = null;

    public function getCalories(): ?string
    {
        return $this->calories;
    }

    public function setCalories(?string $calories): self
    {
        $this->calories = $calories;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $log_date = null;

    public function getLog_date(): ?\DateTimeInterface
    {
        return $this->log_date;
    }

    public function setLog_date(?\DateTimeInterface $log_date): self
    {
        $this->log_date = $log_date;
        return $this;
    }

#[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
private ?string $protein = null;

    public function getProtein(): ?string
    {
        return $this->protein;
    }

    public function setProtein(?string $protein): self
    {
        $this->protein = $protein;
        return $this;
    }

#[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
private ?string $fat = null;

    public function getFat(): ?string
    {
        return $this->fat;
    }

    public function setFat(?string $fat): self
    {
        $this->fat = $fat;
        return $this;
    }

#[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
private ?string $carbs = null;

    public function getCarbs(): ?string
    {
        return $this->carbs;
    }

    public function setCarbs(?string $carbs): self
    {
        $this->carbs = $carbs;
        return $this;
    }

}
