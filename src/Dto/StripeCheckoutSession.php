<?php

namespace App\Dto;

final class StripeCheckoutSession
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $url,
        private readonly string $paymentStatus,
        private readonly ?string $clientReferenceId,
        private readonly int $amountTotal,
        private readonly string $currency,
        private readonly ?string $customerEmail,
        private readonly array $metadata = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function getClientReferenceId(): ?string
    {
        return $this->clientReferenceId;
    }

    public function getAmountTotal(): int
    {
        return $this->amountTotal;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus === 'paid';
    }
}
