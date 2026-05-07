<?php

namespace App\Dto;

final class StripeCheckoutRequest
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        private readonly string $orderId,
        private readonly float $amount,
        private readonly string $currency,
        private readonly string $productName,
        private readonly string $successUrl,
        private readonly string $cancelUrl,
        private readonly ?string $customerEmail = null,
        private readonly ?string $customerName = null,
        private readonly array $metadata = [],
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getSuccessUrl(): string
    {
        return $this->successUrl;
    }

    public function getCancelUrl(): string
    {
        return $this->cancelUrl;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
