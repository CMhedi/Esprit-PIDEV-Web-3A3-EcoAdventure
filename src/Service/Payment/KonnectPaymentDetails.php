<?php

namespace App\Service\Payment;

final class KonnectPaymentDetails
{
    public function __construct(
        private readonly string $paymentRef,
        private readonly string $status,
        private readonly ?string $orderId,
        private readonly int $amount,
        private readonly int $reachedAmount,
        private readonly string $token,
    ) {
    }

    public function getPaymentRef(): string
    {
        return $this->paymentRef;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getReachedAmount(): int
    {
        return $this->reachedAmount;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function coversAmount(int $expectedAmount): bool
    {
        return max($this->amount, $this->reachedAmount) >= $expectedAmount;
    }
}
