<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Http\Request;
use MajorDoMo\Auth\UserRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Http\Middleware\AuthenticateAdmin;
use MajorDoMo\Http\Session\ArraySession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отзыв сессий проверяется на реальных пользователях SQLite без HTTP-сервера.
 *
 * 2026-10-26 — удаление, снятие прав, смена пароля и неверный stamp закрывают доступ.
 */
final class SessionSecurityTest extends TestCase
{
    private UserRepository $users;
    private ArraySession $session;
    private AuthenticateAdmin $middleware;

    protected function setUp(): void
    {
        $database = new DatabaseManager(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_26_000008_create_users.php';
        $migration($database->connection());
        $this->users = new UserRepository($database);
        $this->users->create('admin', 'Администратор', 'secret123', true);
        $this->session = new ArraySession();
        $user = $this->users->verifyAdmin('admin', 'secret123');
        self::assertNotNull($user);
        $this->session->login($user);
        $this->middleware = new AuthenticateAdmin($this->session, $this->users);
    }

    #[DataProvider('accountChanges')]
    public function testAccountChangeRevokesExistingSession(string $change): void
    {
        self::assertSame(204, $this->request()->getStatusCode());
        match ($change) {
            'delete' => $this->users->delete(1),
            'demote' => $this->users->update(1, 'admin', 'Администратор', null, false),
            'password' => $this->users->update(1, 'admin', 'Администратор', 'newsecret123', true),
            default => self::fail('Неизвестное изменение пользователя: ' . $change),
        };

        self::assertSame(401, $this->request()->getStatusCode());
        self::assertNull($this->session->user());
        $browser = $this->middleware->handle(
            Request::create('/admin'),
            static fn (): Response => new Response('', 204)
        );
        self::assertSame(302, $browser->getStatusCode());
        self::assertSame('/login', $browser->headers->get('Location'));
    }

    public function testMissingOrMalformedStampDoesNotBypassAuthentication(): void
    {
        $user = $this->session->user();
        self::assertNotNull($user);
        foreach ([[], ['stamp' => null], ['stamp' => 42], ['stamp' => 'wrong']] as $stamp) {
            $stored = $user;
            unset($stored['stamp']);
            // Проверяем также испорченное содержимое хранилища сессий.
            /** @phpstan-ignore argument.type */
            $this->session->login(array_replace($stored, $stamp));
            self::assertSame(401, $this->request()->getStatusCode());
            self::assertNull($this->session->user());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function accountChanges(): array
    {
        return ['удаление' => ['delete'], 'снятие прав' => ['demote'], 'смена пароля' => ['password']];
    }

    private function request(): Response
    {
        return $this->middleware->handle(
            Request::create('/admin', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']),
            static fn (): Response => new Response('', 204)
        );
    }
}
