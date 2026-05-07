<?php

namespace App\Tests\Static;

use App\Controller\ReservationAdminController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationActiviteCrudStaticTest extends TestCase
{
    public function testReservationActiviteAdminRoutesExposeCrudActions(): void
    {
        $controller = new \ReflectionClass(ReservationAdminController::class);
        self::assertSame('/admin/reservations', $this->routeFor($controller)->getPath());

        self::assertCrudRoute('index', '/', 'app_admin_reservations', []);
        self::assertCrudRoute('edit', '/edit/{id}', 'app_admin_reservation_edit', ['GET', 'POST']);
        self::assertCrudRoute('delete', '/delete/{id}', 'app_admin_reservation_delete', ['POST']);
    }

    public function testReservationActiviteTemplatesAndPersistenceOperationsExist(): void
    {
        self::assertFileExists(self::projectPath('templates/admin/reservationadmin.html.twig'));
        self::assertFileExists(self::projectPath('templates/admin/reservation_edit_modal.html.twig'));

        $source = file_get_contents(self::projectPath('src/Controller/ReservationAdminController.php'));
        $template = file_get_contents(self::projectPath('templates/admin/reservationadmin.html.twig'));

        self::assertIsString($source);
        self::assertIsString($template);
        self::assertStringContainsString('$this->isCsrfTokenValid', $source);
        self::assertStringContainsString('delete_reservation_', $template);
        self::assertStringContainsString('$em->remove($reservation)', $source);
        self::assertStringContainsString('$reservation->setDateRes', $source);
        self::assertStringContainsString('$reservation->setStatutRes', $source);
        self::assertStringContainsString('$reservation->setNbPersonnes', $source);
        self::assertStringContainsString('$em->flush()', $source);
    }

    private static function assertCrudRoute(string $method, string $path, string $name, array $methods): void
    {
        $route = self::routeFor(new \ReflectionMethod(ReservationAdminController::class, $method));

        self::assertSame($path, $route->getPath(), $method . ' path');
        self::assertSame($name, $route->getName(), $method . ' name');
        self::assertSame($methods, $route->getMethods(), $method . ' methods');
    }

    private static function routeFor(\ReflectionClass|\ReflectionMethod $reflection): Route
    {
        $attributes = $reflection->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);

        self::assertNotEmpty($attributes, $reflection->getName() . ' route attribute');

        return $attributes[0]->newInstance();
    }

    private static function projectPath(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
