<?php

namespace App\Entity;

use SensitiveParameter;
use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
class ResetPasswordRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: false)]
    private ?UserApp $user = null;

    #[ORM\Column(length: 20)]
    private string $selector = '';

    #[Ignore]
    #[ORM\Column(length: 255)]
    private string $hashedToken = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(?UserApp $user = null, ?\DateTimeInterface $expiresAt = null, string $selector = '', string $hashedToken = '')
    {
        $this->user = $user;
        $this->requestedAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt instanceof \DateTimeImmutable
            ? $expiresAt
            : \DateTimeImmutable::createFromInterface($expiresAt ?? new \DateTimeImmutable('+1 hour'));
        $this->selector = $selector;
        $this->hashedToken = $hashedToken;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function setSelector(string $selector): self
    {
        $this->selector = $selector;

        return $this;
    }

    public function getHashedToken(): string
    {
        return $this->hashedToken;
    }

    public function setHashedToken(#[SensitiveParameter] string $hashedToken): self
    {
        $this->hashedToken = $hashedToken;

        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

}
