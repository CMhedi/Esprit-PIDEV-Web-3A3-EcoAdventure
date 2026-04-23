<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KonnectPaymentGateway
{
    private const TND_MULTIPLIER = 1000;
    private const CENT_MULTIPLIER = 100;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $walletId,
        private readonly string $apiBaseUrl,
        private readonly string $token = 'TND',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '' && trim($this->walletId) !== '';
    }

    /**
     * @return string[]
     */
    public function getMissingConfigurationFields(): array
    {
        $missing = [];

        if (trim($this->apiKey) === '') {
            $missing[] = 'KONNECT_API_KEY';
        }

        if (trim($this->walletId) === '') {
            $missing[] = 'KONNECT_WALLET_ID';
        }

        return $missing;
    }

    public function getToken(): string
    {
        return strtoupper(trim($this->token) ?: 'TND');
    }

    public function amountToSmallestUnit(float $amount): int
    {
        $multiplier = $this->getToken() === 'TND' ? self::TND_MULTIPLIER : self::CENT_MULTIPLIER;

        return (int) round($amount * $multiplier);
    }

    public function initiatePayment(KonnectPaymentRequest $request): KonnectPaymentSession
    {
        $this->assertConfigured();

        $payload = [
            'receiverWalletId' => $this->walletId,
            'token' => $this->getToken(),
            'amount' => $this->amountToSmallestUnit($request->getAmount()),
            'type' => 'immediate',
            'description' => $request->getDescription(),
            'acceptedPaymentMethods' => ['wallet', 'bank_card', 'e-DINAR'],
            'lifespan' => 20,
            'checkoutForm' => true,
            'addPaymentFeesToAmount' => false,
            'orderId' => $request->getOrderId(),
            'webhook' => $request->getWebhookUrl(),
            'theme' => 'light',
        ];

        $this->addOptionalPayloadField($payload, 'firstName', $request->getFirstName());
        $this->addOptionalPayloadField($payload, 'lastName', $request->getLastName());
        $this->addOptionalPayloadField($payload, 'email', $request->getEmail());
        $this->addOptionalPayloadField($payload, 'phoneNumber', $request->getPhoneNumber());

        try {
            $response = $this->httpClient->request('POST', $this->endpoint('/payments/init-payment'), [
                'headers' => $this->headers(),
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|DecodingExceptionInterface $exception) {
            throw new PaymentGatewayException('Impossible de contacter le service de paiement Konnect.', 0, $exception);
        }

        if ($statusCode >= 400) {
            throw new PaymentGatewayException($this->errorMessage($data, 'Le service Konnect a refuse la creation du paiement.'));
        }

        $payUrl = (string) ($data['payUrl'] ?? '');
        $paymentRef = (string) ($data['paymentRef'] ?? '');

        if ($payUrl === '' || $paymentRef === '') {
            throw new PaymentGatewayException('La reponse Konnect ne contient pas les donnees de redirection attendues.');
        }

        return new KonnectPaymentSession($payUrl, $paymentRef);
    }

    public function getPaymentDetails(string $paymentRef): KonnectPaymentDetails
    {
        $this->assertConfigured();

        $paymentRef = trim($paymentRef);
        if ($paymentRef === '') {
            throw new PaymentGatewayException('Reference paiement manquante.');
        }

        try {
            $response = $this->httpClient->request('GET', $this->endpoint('/payments/' . rawurlencode($paymentRef)), [
                'headers' => $this->headers(),
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|DecodingExceptionInterface $exception) {
            throw new PaymentGatewayException('Impossible de verifier le paiement Konnect.', 0, $exception);
        }

        if ($statusCode >= 400) {
            throw new PaymentGatewayException($this->errorMessage($data, 'Konnect a refuse la verification du paiement.'));
        }

        $payment = is_array($data['payment'] ?? null) ? $data['payment'] : [];

        return new KonnectPaymentDetails(
            $paymentRef,
            strtolower((string) ($payment['status'] ?? 'unknown')),
            isset($payment['orderId']) ? (string) $payment['orderId'] : null,
            (int) ($payment['amount'] ?? 0),
            (int) ($payment['reachedAmount'] ?? 0),
            strtoupper((string) ($payment['token'] ?? $this->getToken())),
        );
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new PaymentGatewayConfigurationException('Konnect n est pas configure. Renseignez KONNECT_API_KEY et KONNECT_WALLET_ID.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function addOptionalPayloadField(array &$payload, string $key, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value !== '') {
            $payload[$key] = $value;
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'x-api-key' => $this->apiKey,
        ];
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->apiBaseUrl, '/') . $path;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function errorMessage(array $data, string $fallback): string
    {
        foreach (['message', 'error', 'details'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }

        return $fallback;
    }
}
