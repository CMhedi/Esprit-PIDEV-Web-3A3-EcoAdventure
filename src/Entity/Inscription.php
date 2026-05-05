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
    public const PAYMENT_GATEWAY_STRIPE = 'stripe';
    public const PAYMENT_GATEWAY_KONNECT = 'konnect';
    public const PAYMENT_GATEWAY_CARD_DEMO = 'card_demo';
    public const PAYMENT_STATUS_INITIATED = 'initiated';
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_AMOUNT_MISMATCH = 'amount_mismatch';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_inscription', type: 'integer')]
    private ?int $id_inscription = null;

    #[ORM\Column(name: 'date_inscription', type: 'datetime')]
    private ?\DateTimeInterface $date_inscription = null;

    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[ORM\Column(name: 'statut_inscr', type: 'string', length: 255)]
    private ?string $statut_inscr = null;

    #[ORM\Column(name: 'montant_total', type: 'decimal', precision: 10, scale: 2)]
    private ?string $montant_total = null;

    #[Assert\NotBlank(message: 'Le nom utilisateur est obligatoire.')]
    #[Assert\Length(min: 3, max: 255)]
    #[ORM\Column(name: 'nom_user', type: 'string', length: 255, nullable: true)]
    private ?string $nom_user = null;

    #[ORM\Column(name: 'nom_pack', type: 'string', length: 255, nullable: true)]
    private ?string $nom_pack = null;

    #[ORM\Column(name: 'payment_gateway', type: 'string', length: 40, nullable: true)]
    private ?string $payment_gateway = null;

    #[ORM\Column(name: 'payment_reference', type: 'string', length: 120, nullable: true)]
    private ?string $payment_reference = null;

    #[ORM\Column(name: 'payment_order_id', type: 'string', length: 120, nullable: true)]
    private ?string $payment_order_id = null;

    #[ORM\Column(name: 'payment_status', type: 'string', length: 40, nullable: true)]
    private ?string $payment_status = null;

    #[ORM\Column(name: 'paid_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paid_at = null;

    #[ORM\Column(name: 'card_image', type: 'string', length: 255, nullable: true)]
    private ?string $card_image = null;

    #[Assert\NotNull(message: 'Le pack est obligatoire.')]
    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'inscriptions')]
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
        return $this->statut_inscr !== null ? StatutInscription::tryFrom($this->statut_inscr) : null;
    }

    public function setStatutInscr(StatutInscription|string $statut_inscr): self
    {
        $this->statut_inscr = $statut_inscr instanceof StatutInscription ? $statut_inscr->value : $statut_inscr;
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

    public function getPaymentGateway(): ?string
    {
        return $this->payment_gateway;
    }

    public function setPaymentGateway(?string $payment_gateway): self
    {
        $this->payment_gateway = $payment_gateway !== null ? trim($payment_gateway) : null;
        return $this;
    }

    public function getPaymentReference(): ?string
    {
        return $this->payment_reference;
    }

    public function setPaymentReference(?string $payment_reference): self
    {
        $this->payment_reference = $payment_reference !== null ? trim($payment_reference) : null;
        return $this;
    }

    public function getPaymentOrderId(): ?string
    {
        return $this->payment_order_id;
    }

    public function setPaymentOrderId(?string $payment_order_id): self
    {
        $this->payment_order_id = $payment_order_id !== null ? trim($payment_order_id) : null;
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->payment_status;
    }

    public function setPaymentStatus(?string $payment_status): self
    {
        $this->payment_status = $payment_status !== null ? trim($payment_status) : null;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paid_at;
    }

    public function setPaidAt(?\DateTimeImmutable $paid_at): self
    {
        $this->paid_at = $paid_at;
        return $this;
    }

    public function getCardImage(): ?string
    {
        return $this->card_image;
    }

    public function setCardImage(?string $card_image): self
    {
        $this->card_image = $card_image;
        return $this;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
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
