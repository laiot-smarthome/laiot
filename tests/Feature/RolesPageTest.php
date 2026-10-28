<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Clickfwd\Yoyo\Yoyo;
use Illuminate\Http\Request;
use MajorDoMo\Auth\RoleRepository;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Database\Migrator;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Роли управляются из пользователей через Yoyo с серверной проверкой прав.
 *
 * 2026-10-28 — ошибки операций проверяются в событии Notify.
 * 2026-10-27 — вместо справочной страницы проверяется полный CRUD ролей.
 */
final class RolesPageTest extends TestCase
{
    private Application $app;
    private ArraySession $session;
    private RoleRepository $roles;

    protected function setUp(): void
    {
        Yoyo::request()->reset();
        $this->session = new ArraySession();
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true, 'log' => 'php://stderr', 'session' => $this->session,
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        (new Migrator($this->app->database(), dirname(__DIR__, 2) . '/database/migrations'))->migrate();
        $users = $this->app->container()->make(UserRepository::class);
        $users->create('sysadmin', 'Администратор', 'secret!123', true);
        $user = $users->verifyAdmin('sysadmin', 'secret!123');
        self::assertNotNull($user);
        $this->session->login($user);
        $this->roles = $this->app->container()->make(RoleRepository::class);
    }

    protected function tearDown(): void
    {
        Yoyo::request()->reset();
        unset($_SERVER['HTTP_HX_REQUEST'], $_SERVER['REQUEST_METHOD']);
        $_REQUEST = [];
        parent::tearDown();
    }

    public function testRoleCanBeCreatedEditedAssignedAndDeleted(): void
    {
        $db = $this->app->database()->connection();
        $panel = (int)$db->table('permissions')->where('name', 'panel.view')->value('id');
        $settings = (int)$db->table('permissions')->where('name', 'settings.manage')->value('id');
        $page = $this->action('show', [], 'GET');
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Роли и права', (string)$page->getContent());
        self::assertStringContainsString('Администратор', (string)$page->getContent());
        $saved = $this->action('save', ['role_name' => 'operator', 'role_title' => 'Оператор', 'permission_ids' => [$panel]]);
        self::assertSame(200, $saved->getStatusCode());
        $id = (int)$db->table('roles')->where('name', 'operator')->value('id');
        self::assertGreaterThan(0, $id);
        self::assertSame([$panel], $this->roles->permissionsByRole()[$id]);
        $form = $this->action('edit', ['actionArgs' => [$id]], 'GET');
        self::assertStringContainsString('Оператор', (string)$form->getContent());
        $this->action('save', ['editing' => $id, 'role_name' => 'operator', 'role_title' => 'Оператор 2', 'permission_ids' => [$settings]]);
        self::assertSame([$settings], $this->roles->permissionsByRole()[$id]);
        $this->roles->syncUserRoles(1, [$id]);
        self::assertSame(['settings.manage'], $this->roles->permissionsForUser(1));
        $denied = $this->action('remove', ['actionArgs' => [$id]]);
        self::assertStringContainsString('Сначала снимите', (string)json_encode(json_decode((string)$denied->headers->get('Yoyo-Browser-Event'), true), JSON_UNESCAPED_UNICODE));
        self::assertNotNull($this->roles->find($id));
        $this->roles->syncUserRoles(1, []);
        $this->action('remove', ['actionArgs' => [$id]]);
        self::assertNull($this->roles->find($id));
        self::assertArrayNotHasKey($id, $this->roles->permissionsByRole());
    }

    public function testSystemRoleAndInvalidPermissionAreProtected(): void
    {
        $admin = (int)$this->app->database()->connection()->table('roles')->where('name', 'admin')->value('id');
        $response = $this->action('remove', ['actionArgs' => [$admin]]);
        self::assertStringContainsString('Системную роль', (string)json_encode(json_decode((string)$response->headers->get('Yoyo-Browser-Event'), true), JSON_UNESCAPED_UNICODE));
        self::assertNotNull($this->roles->find($admin));
        $response = $this->action('save', ['role_name' => 'operator', 'role_title' => 'Оператор', 'permission_ids' => [999999]]);
        self::assertStringContainsString('больше недоступны', (string)json_encode(json_decode((string)$response->headers->get('Yoyo-Browser-Event'), true), JSON_UNESCAPED_UNICODE));
        self::assertSame(2, count($this->roles->allRoles()));
    }

    public function testMutationRequiresPostCsrfAndPermission(): void
    {
        $data = ['role_name' => 'operator', 'role_title' => 'Оператор'];
        $response = $this->action('save', $data, 'GET');
        self::assertStringContainsString('только POST', (string)json_encode(json_decode((string)$response->headers->get('Yoyo-Browser-Event'), true), JSON_UNESCAPED_UNICODE));
        self::assertSame(2, count($this->roles->allRoles()));
        self::assertSame(403, $this->action('save', $data, 'POST', false)->getStatusCode());
        $this->roles->syncUserRoles(1, []);
        $user = $this->session->user();
        self::assertNotNull($user);
        $user['is_admin'] = false;
        $this->session->login($user);
        $response = $this->action('save', $data);
        self::assertStringContainsString('Требуется право', (string)$response->getContent());
        self::assertSame(2, count($this->roles->allRoles()));
        $this->session->logout();
        self::assertSame(401, $this->action('save', $data)->getStatusCode());
    }

    public function testDeletedRolesAndPermissionsDoNotGrantAccess(): void
    {
        $db = $this->app->database()->connection();
        $db->table('roles')->where('name', 'admin')->update(['deleted_at' => date('Y-m-d H:i:s')]);
        self::assertSame([], $this->roles->permissionsForUser(1));
        self::assertSame([], $this->roles->rolesForUser(1));
        $db->table('roles')->where('name', 'admin')->update(['deleted_at' => null]);
        $db->table('permissions')->where('name', '*')->update(['deleted_at' => date('Y-m-d H:i:s')]);
        self::assertSame([], $this->roles->permissionsForUser(1));
    }

    /** @param array<string,mixed> $params */
    private function action(string $action, array $params = [], string $method = 'POST', bool $csrf = true): Response
    {
        Yoyo::request()->reset();
        $_REQUEST = ['component' => 'user-roles/' . $action, 'yoyo-id' => 'test-user-roles', 'open' => true] + $params;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_HX_REQUEST'] = 'true';
        Yoyo::request()->mock($_REQUEST, $_SERVER);

        return $this->app->handle(Request::create('/yoyo', $method, [], [], [], $csrf ? ['HTTP_X_CSRF_TOKEN' => $this->session->csrfToken()] : []));
    }
}
