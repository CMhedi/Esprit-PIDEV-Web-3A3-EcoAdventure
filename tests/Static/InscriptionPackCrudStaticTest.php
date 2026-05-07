<?php

namespace App\Tests\Static;

use App\Controller\InscriptionAdminController;
use App\Controller\PackInscriptionController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class InscriptionPackCrudStaticTest extends TestCase
{
    public function testPackInscriptionUserRoutesCoverCreatePaymentAndReceiptFlow(): void
    {
        self::assertRoute(PackInscriptionController::class, 'inscrire', '/packs/{id}/inscription', 'app_pack_inscription', ['GET', 'POST']);
        self::assertRoute(PackInscriptionController::class, 'payment', '/inscriptions/{id}/payment', 'app_pack_inscription_payment', ['GET']);
        self::assertRoute(PackInscriptionController::class, 'stripePayment', '/inscriptions/{id}/payment/stripe', 'app_pack_inscription_payment_stripe', ['POST']);
        self::assertRoute(PackInscriptionController::class, 'demoCardPayment', '/inscriptions/{id}/payment/card-demo', 'app_pack_inscription_card_demo', ['POST']);
        self::assertRoute(PackInscriptionController::class, 'paymentCallback', '/packs/inscription/payment/konnect/callback', 'app_pack_inscription_payment_callback', ['GET']);
        self::assertRoute(PackInscriptionController::class, 'publicTicket', '/inscriptions/{id}/ticket/{token}', 'app_pack_inscription_ticket', ['GET']);
    }

    public function testAdminInscriptionRoutesCoverListAndDelete(): void
    {
        self::assertRoute(InscriptionAdminController::class, 'inscriptions', '/admin/inscriptions', 'app_admin_inscriptions', ['GET']);
        self::assertRoute(InscriptionAdminController::class, 'deleteInscription', '/admin/inscriptions/{id}/delete', 'app_admin_inscription_delete', ['POST']);
    }

    public function testInscriptionPackTemplatesAndPersistenceOperationsExist(): void
    {
        foreach ([
            'templates/front/hedisPackInscription/pack_inscription.html.twig',
            'templates/front/hedisPackInscription/pack_payment.html.twig',
            'templates/front/hedisPackInscription/ticket.html.twig',
            'templates/admin/inscriptions/InscriptionPacks.html.twig',
        ] as $template) {
            self::assertFileExists(self::projectPath($template));
        }

        $frontSource = file_get_contents(self::projectPath('src/Controller/PackInscriptionController.php'));
        $adminSource = file_get_contents(self::projectPath('src/Controller/InscriptionAdminController.php'));

        self::assertIsString($frontSource);
        self::assertIsString($adminSource);
        self::assertStringContainsString('$entityManager->persist($inscription)', $frontSource);
        self::assertStringContainsString('createPendingInscription', $frontSource);
        self::assertStringContainsString('assertInscriptionOwnership', $frontSource);
        self::assertStringContainsString('$this->isCsrfTokenValid', $frontSource);
        self::assertStringContainsString('$entityManager->remove($inscription)', $adminSource);
        self::assertStringContainsString('delete_inscription_', $adminSource);
        self::assertStringContainsString('$entityManager->flush()', $adminSource);
    }

    /**
     * @param class-string $controller
     * @param list<string> $methods
     */
    private static function assertRoute(string $controller, string $method, string $path, string $name, array $methods): void
    {
        $route = self::firstRouteFor(new \ReflectionMethod($controller, $method), $name);

        self::assertSame($path, $route->getPath(), $method . ' path');
        self::assertSame($name, $route->getName(), $method . ' name');
        self::assertSame($methods, $route->getMethods(), $method . ' methods');
    }

    private static function firstRouteFor(\ReflectionMethod $reflection, string $name): Route
    {
        $attributes = $reflection->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            $route = $attribute->newInstance();
            if ($route->getName() === $name) {
                return $route;
            }
        }

        self::fail($reflection->getName() . ' route ' . $name . ' not found');
    }

    private static function projectPath(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
