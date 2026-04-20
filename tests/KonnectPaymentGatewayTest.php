<?php

namespace App\Tests;

use App\Service\Payment\KonnectPaymentGateway;
use App\Service\Payment\KonnectPaymentRequest;
use App\Service\Payment\PaymentGatewayConfigurationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KonnectPaymentGatewayTest extends TestCase
{
    public function testInitiatePaymentBuildsKonnectPayloadInMillimes(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'payUrl' => 'https://pay.konnect.test/session',
                'paymentRef' => 'pay_123',
            ], JSON_THROW_ON_ERROR));
        });

        $gateway = new KonnectPaymentGateway($client, 'secret-key', 'wallet-123', 'https://api.konnect.test/api/v2', 'TND');

        $session = $gateway->initiatePayment(new KonnectPaymentRequest(
            'PACK-1-ABC',
            125.500,
            'Inscription EcoAdventure',
            'https://app.test/payment/callback',
            'Jane',
            'Doe',
            'jane@example.com',
        ));

        self::assertSame('https://pay.konnect.test/session', $session->getPayUrl());
        self::assertSame('pay_123', $session->getPaymentRef());
        self::assertSame('POST', $requests[0][0]);
        self::assertSame('https://api.konnect.test/api/v2/payments/init-payment', $requests[0][1]);

        $payload = $this->extractPayload($requests[0][2]);
        self::assertSame('wallet-123', $payload['receiverWalletId']);
        self::assertSame('TND', $payload['token']);
        self::assertSame(125500, $payload['amount']);
        self::assertSame('PACK-1-ABC', $payload['orderId']);
        self::assertSame('https://app.test/payment/callback', $payload['webhook']);
    }

    public function testGetPaymentDetailsNormalizesPaymentStatus(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
            'payment' => [
                'status' => 'completed',
                'orderId' => 'PACK-1-ABC',
                'amount' => 125500,
                'reachedAmount' => 125500,
                'token' => 'TND',
            ],
        ], JSON_THROW_ON_ERROR)));

        $gateway = new KonnectPaymentGateway($client, 'secret-key', 'wallet-123', 'https://api.konnect.test/api/v2', 'TND');
        $details = $gateway->getPaymentDetails('pay_123');

        self::assertTrue($details->isCompleted());
        self::assertTrue($details->coversAmount(125500));
        self::assertSame('PACK-1-ABC', $details->getOrderId());
        self::assertSame('TND', $details->getToken());
    }

    public function testMissingConfigurationFailsBeforeHttpCall(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(''));
        $gateway = new KonnectPaymentGateway($client, '', '', 'https://api.konnect.test/api/v2', 'TND');

        $this->expectException(PaymentGatewayConfigurationException::class);

        $gateway->initiatePayment(new KonnectPaymentRequest(
            'PACK-1-ABC',
            125.500,
            'Inscription EcoAdventure',
            'https://app.test/payment/callback',
        ));
    }

    public function testMissingConfigurationFieldsExposeActivationHints(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(''));
        $gateway = new KonnectPaymentGateway($client, '', 'wallet-123', 'https://api.konnect.test/api/v2', 'TND');

        self::assertSame(['KONNECT_API_KEY'], $gateway->getMissingConfigurationFields());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function extractPayload(array $options): array
    {
        if (isset($options['json']) && is_array($options['json'])) {
            return $options['json'];
        }

        return json_decode((string) ($options['body'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
    }
}
