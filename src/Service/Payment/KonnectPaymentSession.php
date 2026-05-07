<?php

namespace App\Service\Payment;

final class KonnectPaymentSession
{
    public function __construct(
        private readonly string $payUrl,
        private readonly string $paymentRef,
    ) {
    }

    public function getPayUrl(): string
    {
        return $this->payUrl;
    }

    public function getPaymentRef(): string
    {
        return $this->paymentRef;
    }
}
