<?php

namespace App\Tests\Controller;

use App\Controller\PackInscriptionController;
use App\Entity\Pack;
use PHPUnit\Framework\TestCase;

final class PackInscriptionControllerPrivateLogicTest extends TestCase
{
    public function testBuildPaymentOrderIdUsesPackIdentifierPrefix(): void
    {
        $controller = new PackInscriptionController();
        $pack = new Pack();
        $this->setEntityId($pack, 'id_pack', 42);

        $paymentOrderId = $this->invokePrivateMethod($controller, 'buildPaymentOrderId', [$pack]);

        self::assertMatchesRegularExpression('/^PACK-42-[A-F0-9]{10}$/', $paymentOrderId);
    }

    public function testValidDemoCardPayloadAcceptsTypicalDemoVisaCard(): void
    {
        $controller = new PackInscriptionController();

        $isValid = $this->invokePrivateMethod($controller, 'isValidDemoCardPayload', [
            'demo_card_server_abcd',
            '4242424242424242',
            '12/30',
            '123',
            '4242',
        ]);

        self::assertTrue($isValid);
    }

    public function testValidDemoCardPayloadRejectsBadTokenAndExpiry(): void
    {
        $controller = new PackInscriptionController();

        self::assertFalse($this->invokePrivateMethod($controller, 'isValidDemoCardPayload', [
            'stripe_token',
            '4242424242424242',
            '12/30',
            '123',
            '4242',
        ]));

        self::assertFalse($this->invokePrivateMethod($controller, 'isValidDemoCardPayload', [
            'demo_card_server_abcd',
            '4242424242424242',
            '13/30',
            '123',
            '4242',
        ]));
    }

    public function testDetectDemoCardBrandRecognizesVisaMastercardAndFallback(): void
    {
        $controller = new PackInscriptionController();

        self::assertSame('Visa', $this->invokePrivateMethod($controller, 'detectDemoCardBrand', ['4242424242424242']));
        self::assertSame('Mastercard', $this->invokePrivateMethod($controller, 'detectDemoCardBrand', ['5555555555554444']));
        self::assertSame('Carte', $this->invokePrivateMethod($controller, 'detectDemoCardBrand', ['9912345678901234']));
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivateMethod(object $instance, string $methodName, array $arguments): mixed
    {
        $method = new \ReflectionMethod($instance, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($instance, $arguments);
    }

    private function setEntityId(object $entity, string $property, int $value): void
    {
        $reflection = new \ReflectionProperty($entity, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }
}
