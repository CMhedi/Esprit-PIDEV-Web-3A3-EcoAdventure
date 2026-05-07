<?php

namespace App\Tests\Static;

use App\Controller\UserAppController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class UserAppCrudStaticTest extends TestCase
{
    public function testCrudControllerExposesExpectedRoutes(): void
    {
        $controller = new \ReflectionClass(UserAppController::class);
        $classRoute = $this->routeFor($controller);

        self::assertSame('/user/app', $classRoute->getPath());

        self::assertCrudRoute('index', null, 'app_user_app_index', ['GET']);
        self::assertCrudRoute('new', '/new', 'app_user_app_new', ['GET', 'POST']);
        self::assertCrudRoute('show', '/{id_user}', 'app_user_app_show', ['GET']);
        self::assertCrudRoute('edit', '/{id_user}/edit', 'app_user_app_edit', ['GET', 'POST']);
        self::assertCrudRoute('delete', '/{id_user}', 'app_user_app_delete', ['POST']);
    }

    public function testCrudTemplatesExist(): void
    {
        $templatesDirectory = self::projectPath('templates/user_app');

        foreach (['index', 'new', 'show', 'edit', '_form', '_delete_form'] as $template) {
            self::assertFileExists($templatesDirectory . '/' . $template . '.html.twig');
        }
    }

    public function testCrudControllerKeepsPersistenceAndCsrfGuards(): void
    {
        $source = file_get_contents(self::projectPath('src/Controller/UserAppController.php'));

        self::assertIsString($source);
        self::assertStringContainsString('UserAppType::class', $source);
        self::assertStringContainsString('$entityManager->persist($userApp)', $source);
        self::assertStringContainsString('$entityManager->flush()', $source);
        self::assertStringContainsString('$this->isCsrfTokenValid', $source);
        self::assertStringContainsString('$entityManager->remove($userApp)', $source);
    }

    private static function assertCrudRoute(string $method, ?string $path, string $name, array $methods): void
    {
        $route = self::routeFor(new \ReflectionMethod(UserAppController::class, $method));

        self::assertSame($path, $route->getPath(), $method . ' path');
        self::assertSame($name, $route->getName(), $method . ' name');
        self::assertSame($methods, $route->getMethods(), $method . ' methods');
    }

    private static function routeFor(\ReflectionClass|\ReflectionMethod $reflection): Route
    {
        $attributes = $reflection->getAttributes(Route::class);

        self::assertNotEmpty($attributes, $reflection->getName() . ' route attribute');

        return $attributes[0]->newInstance();
    }

    private static function projectPath(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
