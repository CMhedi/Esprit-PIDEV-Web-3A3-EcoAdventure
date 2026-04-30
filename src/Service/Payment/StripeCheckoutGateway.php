<?php

namespace App\Service\Payment;

use App\Dto\StripeCheckoutRequest;
use App\Dto\StripeCheckoutSession;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class StripeCheckoutGateway
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $secretKey,
        private readonly string $publishableKey = '',
        private readonly string $currency = 'eur',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->secretKey) !== '';
    }

    /**
     * @return string[]
     */
    public function getMissingConfigurationFields(): array
    {
        $missing = [];

        if (trim($this->secretKey) === '') {
            $missing[] = 'STRIPE_SECRET_KEY';
        }

        if (trim($this->publishableKey) === '') {
            $missing[] = 'STRIPE_PUBLISHABLE_KEY';
        }

        return $missing;
    }

    public function getCurrency(): string
    {
        $currency = strtolower(trim($this->currency));

        return $currency !== '' ? $currency : 'eur';
    }

    public function getPublishableKey(): string
    {
        return trim($this->publishableKey);
    }

    public function amountToSmallestUnit(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public function createCheckoutSession(StripeCheckoutRequest $request): StripeCheckoutSession
    {
        $this->assertConfigured();

        $payload = [
            'mode' => 'payment',
            'success_url' => $request->getSuccessUrl(),
            'cancel_url' => $request->getCancelUrl(),
            'client_reference_id' => $request->getOrderId(),
            'customer_email' => trim((string) $request->getCustomerEmail()),
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($request->getCurrency()),
            'line_items[0][price_data][unit_amount]' => $this->amountToSmallestUnit($request->getAmount()),
            'line_items[0][price_data][product_data][name]' => $request->getProductName(),
        ];

        $customerName = trim((string) $request->getCustomerName());
        if ($customerName !== '') {
            $payload['metadata[customer_name]'] = $customerName;
        }

        foreach ($request->getMetadata() as $key => $value) {
            $payload[sprintf('metadata[%s]', $key)] = $value;
        }

        $data = $this->request('POST', 'https://api.stripe.com/v1/checkout/sessions', $payload);

        return $this->hydrateSession($data);
    }

    public function getCheckoutSession(string $sessionId): StripeCheckoutSession
    {
        $this->assertConfigured();

        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new PaymentGatewayException('Session Stripe introuvable.');
        }

        $data = $this->request('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId));

        return $this->hydrateSession($data);
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new PaymentGatewayConfigurationException('Stripe n est pas configure. Renseignez STRIPE_SECRET_KEY.');
        }
    }

    /**
     * @param array<string, scalar|null> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $body = []): array
    {
        try {
            $response = $this->httpClient->request($method, $url, [
                'auth_bearer' => $this->secretKey,
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|DecodingExceptionInterface $exception) {
            throw new PaymentGatewayException('Impossible de contacter Stripe pour ce paiement.', 0, $exception);
        }

        if ($statusCode >= 400) {
            throw new PaymentGatewayException($this->errorMessage($data, 'Stripe a refuse la requete de paiement.'));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateSession(array $data): StripeCheckoutSession
    {
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new PaymentGatewayException('La session Stripe retournee est invalide.');
        }

        $metadata = [];
        if (is_array($data['metadata'] ?? null)) {
            foreach ($data['metadata'] as $key => $value) {
                if (is_scalar($value)) {
                    $metadata[(string) $key] = (string) $value;
                }
            }
        }

        return new StripeCheckoutSession(
            $id,
            isset($data['url']) && is_scalar($data['url']) ? (string) $data['url'] : null,
            strtolower((string) ($data['payment_status'] ?? 'unpaid')),
            isset($data['client_reference_id']) && is_scalar($data['client_reference_id']) ? (string) $data['client_reference_id'] : null,
            (int) ($data['amount_total'] ?? 0),
            strtolower((string) ($data['currency'] ?? $this->getCurrency())),
            isset($data['customer_email']) && is_scalar($data['customer_email']) ? (string) $data['customer_email'] : null,
            $metadata,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function errorMessage(array $data, string $fallback): string
    {
        $error = $data['error'] ?? null;
        if (is_array($error) && isset($error['message']) && is_scalar($error['message'])) {
            return (string) $error['message'];
        }

        if (isset($data['message']) && is_scalar($data['message'])) {
            return (string) $data['message'];
        }

        return $fallback;
    }
}
