<?php

namespace App\Service\Payment;

final class KonnectPaymentRequest
{
    public function __construct(
        private readonly string $orderId,
        private readonly float $amount,
        private readonly string $description,
        private readonly string $webhookUrl,
        private readonly ?string $firstName = null,
        private readonly ?string $lastName = null,
        private readonly ?string $email = null,
        private readonly ?string $phoneNumber = null,
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }
}
