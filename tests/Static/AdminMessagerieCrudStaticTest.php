<?php

namespace App\Tests\Static;

use App\Controller\Admin\AdminMessagerieController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class AdminMessagerieCrudStaticTest extends TestCase
{
    public function testAdminMessagingRoutesExposeModerationCrudActions(): void
    {
        $controller = new \ReflectionClass(AdminMessagerieController::class);
        self::assertSame('/admin/messagerie', $this->routeFor($controller)->getPath());

        self::assertCrudRoute('index', '/', 'admin_messagerie_index', []);
        self::assertCrudRoute('view', '/{id}', 'admin_messagerie_view', []);
        self::assertCrudRoute('delete', '/delete/{id}', 'admin_messagerie_delete', ['POST']);
        self::assertCrudRoute('deleteMessage', '/message/delete/{id}', 'admin_messagerie_delete_message', ['POST']);
        self::assertCrudRoute('banConversation', '/ban/{id}', 'admin_messagerie_ban_conversation', ['POST']);
        self::assertCrudRoute('banUser', '/ban-user/{id}', 'admin_messagerie_ban_user', ['POST']);
        self::assertCrudRoute('stats', '/stats', 'admin_messagerie_stats', []);
    }

    public function testAdminMessagingTemplatesAndModerationGuardsExist(): void
    {
        self::assertFileExists(self::projectPath('templates/admin/messagerie/index.html.twig'));
        self::assertFileExists(self::projectPath('templates/admin/messagerie/view.html.twig'));

        $source = file_get_contents(self::projectPath('src/Controller/Admin/AdminMessagerieController.php'));

        self::assertIsString($source);
        self::assertStringContainsString('$this->isCsrfTokenValid', $source);
        self::assertStringContainsString('$em->remove($conversation)', $source);
        self::assertStringContainsString('$em->remove($message)', $source);
        self::assertStringContainsString('blockPrivateConversation()', $source);
        self::assertStringContainsString('$em->flush()', $source);
    }

    private static function assertCrudRoute(string $method, string $path, string $name, array $methods): void
    {
        $route = self::routeFor(new \ReflectionMethod(AdminMessagerieController::class, $method));

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
