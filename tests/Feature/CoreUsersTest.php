<?php

declare(strict_types=1);

namespace LaIOT\Tests\Feature;

use Illuminate\Http\Request;
use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use LaIOT\Http\Session\ArraySession;
use LaIOT\Services\Auth\RoleRepository;
use LaIOT\Services\Auth\UserRepository;
use LaIOT\Services\Methods\MethodRunner;
use LaIOT\Services\Objects\ObjectRepository;
use LaIOT\Services\Properties\PropertyValueService;
use PHPUnit\Framework\TestCase;

/**
 * Пользователь, роли и объект проверяются на полной изолированной схеме.
 *
 * 2026-10-27 — проверены транзакции, присутствие, переименование и удаление.
 */
final class CoreUsersTest extends TestCase
{
    use DrainsEvents;

    private Application $app;
    private ArraySession $session;
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->app = new Application(dirname(__DIR__, 2), [
            'debug' => true, 'cache' => ['driver' => 'array'], 'log' => 'php://stderr',
            'session' => $this->session,
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        $this->app->boot();
        (new Migrator($this->app->database(), dirname(__DIR__, 2) . '/database/migrations'))->migrate();
        $this->users = $this->app->container()->make(UserRepository::class);
        $this->users->create('sysadmin', 'Администратор', 'secret!123', true);
        $user = $this->users->verifyAdmin('sysadmin', 'secret!123');
        self::assertNotNull($user);
        $this->session->login($user);
    }

    public function testUserObjectPresenceRenameAndDeletion(): void
    {
        $db = $this->app->database()->connection();
        $roleId = (int)$db->table('roles')->where('name', 'user')->value('id');
        $response = $this->post('/users/create', ['username' => 'operator1', 'name' => 'Оператор', 'password' => 'secret!123', 'role_ids' => [$roleId]]);
        self::assertSame(302, $response->getStatusCode());
        $row = $db->table('users')->where('username', 'operator1')->first();
        self::assertNotNull($row);
        $objectId = (int)$row->object_id;
        self::assertGreaterThan(0, $objectId);
        $values = $this->app->container()->make(PropertyValueService::class);
        self::assertSame('0', $values->get('operator1', 'isHome'));
        self::assertSame('0', $values->get('operator1', 'isWifiConnected'));
        $runner = $this->app->container()->make(MethodRunner::class);
        self::assertTrue($runner->run('operator1', 'arrivedHome'));
        self::assertSame('1', $values->get('operator1', 'isHome'));
        self::assertSame('0', $values->get('operator1', 'isWifiConnected'));
        self::assertTrue($runner->run('operator1', 'leftHome'));
        self::assertSame('0', $values->get('operator1', 'isHome'));
        $this->drainEvents($this->app);
        self::assertGreaterThan(0, $db->table('property_history')->where('object_id', $objectId)->count());
        self::assertNull($this->users->verifyAdmin('operator1', 'secret!123'));

        $response = $this->post('/users/' . $row->id . '/edit', ['username' => 'operator2', 'name' => 'Оператор 2', 'password' => '', 'role_ids' => [$roleId]]);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame($objectId, $this->users->find((int)$row->id)['object_id']);
        self::assertSame('operator2', $db->table('objects')->where('id', $objectId)->value('title'));
        self::assertSame('0', $values->get('operator2', 'isHome'));
        self::assertNull($values->get('operator1', 'isHome'));
        self::assertSame(200, $this->app->handle(Request::create('/users/' . $row->id . '/edit'))->getStatusCode());

        $response = $this->post('/users/' . $row->id . '/delete');
        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->users->find((int)$row->id));
        self::assertNotNull($db->table('objects')->where('id', $objectId)->value('deleted_at'));
        self::assertSame(0, $db->table('property_values')->where('object_id', $objectId)->count());
        self::assertSame(0, $db->table('property_history')->where('object_id', $objectId)->count());
        self::assertSame(0, $db->table('user_roles')->where('user_id', $row->id)->count());
        $replacement = $this->users->create('operator2', 'Новый пользователь', 'secret!123', false);
        self::assertNotSame((int)$row->id, $replacement);
    }

    public function testInvalidRoleRollsBackUserObjectValuesAndQueue(): void
    {
        $db = $this->app->database()->connection();
        $pending = $db->table('queue_jobs')->count();
        $response = $this->post('/users/create', ['username' => 'operator1', 'name' => 'Оператор', 'password' => 'secret!123', 'role_ids' => [999999]]);
        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($db->table('users')->where('username', 'operator1')->exists());
        self::assertFalse($db->table('objects')->where('title', 'operator1')->exists());
        self::assertSame($pending, $db->table('queue_jobs')->count());
        self::assertSame(2, $db->table('property_values')->count());
    }

    public function testObjectNameCollisionDoesNotCreateAccount(): void
    {
        $db = $this->app->database()->connection();
        $classId = (int)$db->table('classes')->where('title', 'Users')->value('id');
        $this->app->container()->make(ObjectRepository::class)->create('operator1', $classId, 0, 'Занято');
        $response = $this->post('/users/create', ['username' => 'operator1', 'name' => 'Оператор', 'password' => 'secret!123']);
        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($db->table('users')->where('username', 'operator1')->exists());
        self::assertSame(1, $db->table('objects')->where('title', 'operator1')->count());
    }

    public function testPasswordAndLoginPolicyAndDraftRoles(): void
    {
        $roleId = (int)$this->app->database()->connection()->table('roles')->where('name', 'user')->value('id');
        foreach ([['short', 'secret!123'], ['operator1', 'secret123'], ['user.name', 'secret!123']] as [$username, $password]) {
            $response = $this->post('/users/create', ['username' => $username, 'name' => 'Оператор', 'password' => $password, 'role_ids' => [$roleId]]);
            self::assertSame(422, $response->getStatusCode());
        }
        self::assertSame(1, $this->app->database()->connection()->table('users')->count());
        self::assertSame(302, $this->post('/users/create', ['username' => 'operator1', 'name' => 'Оператор', 'password' => 'secret_1', 'role_ids' => [$roleId]])->getStatusCode());
    }

    public function testSelfRemovalAndDemotionAreRejected(): void
    {
        self::assertSame(422, $this->post('/users/1/delete')->getStatusCode());
        self::assertSame(422, $this->post('/users/1/edit', ['username' => 'sysadmin', 'name' => 'Администратор'])->getStatusCode());
        self::assertTrue($this->users->find(1)['is_admin']);
    }

    public function testSeedAndDefaultRoleAndUnifiedPage(): void
    {
        $db = $this->app->database()->connection();
        self::assertSame(1, $db->table('classes')->where('title', 'Users')->count());
        self::assertSame(2, $db->table('properties')->count());
        self::assertSame(2, $db->table('methods')->count());
        self::assertSame(['admin'], $this->app->container()->make(RoleRepository::class)->rolesForUser(1));
        $response = $this->app->handle(Request::create('/users'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Роли и права', (string)$response->getContent());
        self::assertStringNotContainsString('href="/roles"', (string)$response->getContent());
        self::assertSame(404, $this->app->handle(Request::create('/roles'))->getStatusCode());
    }

    public function testLinkedObjectCannotBeDeletedIndependently(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->app->container()->make(ObjectRepository::class)->delete($this->users->find(1)['object_id']);
    }

    /** @param array<string,mixed> $data */
    private function post(string $url, array $data = []): \Symfony\Component\HttpFoundation\Response
    {
        return $this->app->handle(Request::create($url, 'POST', ['_token' => $this->session->csrfToken()] + $data));
    }
}
