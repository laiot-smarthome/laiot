<?php

declare(strict_types=1);

namespace LaIOT\Services\Install;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator;
use InvalidArgumentException;
use JsonException;
use LaIOT\Database\DatabaseManager;
use LaIOT\Database\Migrator;
use LaIOT\Foundation\Application;
use LaIOT\Foundation\Config;
use LaIOT\Services\Auth\UserRepository;
use LaIOT\Services\Security\EncryptionKey;
use LaIOT\Services\Settings\EnvFile;
use LaIOT\Services\Systemd\SystemdAgentClient;
use PDO;
use PDOException;
use Predis\Client as RedisClient;
use Throwable;

/**
 * Установка LaIOT: окружение, база, Redis, администратор.
 *
 * Сервис общий для страницы `/install` и команды `install`, поэтому правила
 * проверки, порядок записи `.env` и создание администратора не расходятся.
 * Он не зависит от настроенной базы: проверки читают только PHP, файлы и
 * сокет агента, а подключение к БД делается отдельными короткоживущими
 * соединениями.
 *
 * Порядок применения: проверить ввод → записать `.env` → выполнить миграции
 * → создать администратора → записать `var/installed.lock` и удалить ключ.
 * 2026-10-29 — закрытый пресет подключения от SSH-мастера, удаляемый после установки.
 * `.env` пишется до миграций, чтобы после сбоя повторный запуск шёл в ту же
 * базу; признак установки появляется только вместе с администратором.
 *
 * 2026-10-28 — APP_KEY автоматически создаётся один раз при записи окружения.
 * 2026-10-28 — первая реализация установщика вместо alpha `install.php`.
 */
final class InstallService
{
    /** Сокет административного агента; путь задан шаблоном сервиса. */
    public const AGENT_SOCKET = '/run/laiot/agent.sock';

    private const MIN_FREE_BYTES = 268_435_456;

    /** @var Closure(array<string, mixed>): DatabaseManager */
    private readonly Closure $databaseFactory;

    /** @var Closure(array<string, mixed>): Application */
    private readonly Closure $applicationFactory;

    /**
     * @param Closure(array<string, mixed>): DatabaseManager|null $databaseFactory
     * @param Closure(array<string, mixed>): Application|null $applicationFactory
     */
    public function __construct(
        private readonly Application $app,
        private readonly string $storageRoot,
        ?Closure $databaseFactory = null,
        ?Closure $applicationFactory = null,
        private readonly ?string $migrationsPath = null
    ) {
        $this->databaseFactory = $databaseFactory
            ?? static fn (array $config): DatabaseManager => new DatabaseManager($config);
        $this->applicationFactory = $applicationFactory
            ?? fn (array $config): Application => new Application($this->app->root(), $config);
    }

    public function storageRoot(): string
    {
        return $this->storageRoot;
    }

    public function keyPath(): string
    {
        return $this->storageRoot . '/var/install.key';
    }

    public function lockPath(): string
    {
        return $this->storageRoot . '/var/installed.lock';
    }

    public function initialConnection(): ?InstallRequest
    {
        $path = $this->storageRoot . '/var/install-connection.json';
        if (!file_exists($path) && !is_link($path)) {
            return null;
        }
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new InstallException('Подготовленные настройки подключения недоступны.', 'storage');
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new InstallException('Не удалось прочитать подготовленные настройки подключения.', 'storage');
        }
        try {
            $values = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InstallException('Повреждены подготовленные настройки подключения.', 'storage');
        }
        if (!is_array($values)) {
            throw new InstallException('Неверный формат подготовленных настроек подключения.', 'storage');
        }
        $request = InstallRequest::fromArray($values);
        if ($this->validateConnection($request) !== []) {
            throw new InstallException('Подготовленные настройки подключения не прошли проверку.', 'storage');
        }
        return $request;
    }

    public function version(): string
    {
        return (string)$this->app->config('version', 'dev');
    }

    /**
     * Установка считается завершённой, если есть lock или действующий
     * администратор в настроенной базе. Второй признак важен, когда lock
     * потерян: открытый установщик не должен давать создать нового админа.
     */
    public function installed(): bool
    {
        if (is_file($this->lockPath())) {
            return true;
        }

        $database = $this->app->database();
        if (!$database->isConfigured()) {
            return false;
        }

        try {
            if (!$database->ping() || !$database->schema()->hasTable('users')) {
                return false;
            }

            $installed = $database->connection()
                ->table('users')
                ->where('is_admin', 1)
                ->whereNull('deleted_at')
                ->exists();
            if ($installed) {
                // Установка могла быть выполнена без lock (CLI/старая схема).
                // Восстанавливаем маркер, чтобы следующие запросы не ходили в БД.
                $this->rememberInstalled();
            }

            return $installed;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Дешёвый маркер установки для HTTP-проверки на каждом запросе.
     *
     * Пишется только если администратор уже есть, а lock потерян; ошибка
     * записи не мешает работе — тогда проверка продолжит смотреть в БД.
     */
    private function rememberInstalled(): void
    {
        if (is_file($this->lockPath()) || !$this->ensureStorage()) {
            return;
        }
        $payload = json_encode([
            'version' => $this->version(),
            'installed_at' => date('Y-m-d H:i:s'),
            'source' => 'detected',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload !== false) {
            @file_put_contents($this->lockPath(), $payload);
        }
    }

    /**
     * Возвращает ключ установки, создавая его при первом обращении.
     *
     * Ключ читает администратор через файловый менеджер FastPanel или SSH.
     * Пока файла нет, форма установки не показывается: иначе первый, кто
     * открыл URL, создал бы себе администратора.
     */
    public function key(): ?string
    {
        $path = $this->keyPath();
        if (is_file($path)) {
            $stored = trim((string)@file_get_contents($path));
            if (preg_match('/^[a-f0-9]{64}$/D', $stored) === 1) {
                return $stored;
            }
            // Повреждённый файл заменяем новым ключом, а не блокируем установку.
        }

        if (!$this->ensureStorage()) {
            return null;
        }

        $key = bin2hex(random_bytes(32));
        $mask = umask(0077);
        try {
            $written = file_put_contents($path, $key, LOCK_EX);
        } finally {
            umask($mask);
        }
        if ($written === false) {
            return null;
        }
        @chmod($path, 0600);

        return $key;
    }

    public function tokenMatches(string $token): bool
    {
        if ($token === '' || !is_file($this->keyPath())) {
            return false;
        }
        $stored = trim((string)@file_get_contents($this->keyPath()));

        return $stored !== '' && hash_equals($stored, $token);
    }

    /**
     * Проверки окружения, не требующие базы данных.
     *
     * @return list<InstallCheck>
     */
    public function requirements(): array
    {
        $checks = [];
        $checks[] = version_compare(PHP_VERSION, '8.2.0', '>=')
            ? InstallCheck::ok('php', 'PHP', 'Версия ' . PHP_VERSION . ' — подходит')
            : InstallCheck::fail('php', 'PHP', 'Нужен PHP 8.2 или новее, а сейчас ' . PHP_VERSION);

        $required = ['pdo_mysql', 'mbstring', 'intl', 'curl', 'openssl'];
        $optional = ['fileinfo', 'zip'];
        $missingRequired = array_values(array_filter($required, static fn (string $name): bool => !extension_loaded($name)));
        $missingOptional = array_values(array_filter($optional, static fn (string $name): bool => !extension_loaded($name)));
        if ($missingRequired !== []) {
            $checks[] = InstallCheck::fail('extensions', 'Расширения PHP', 'Не хватает: ' . implode(', ', $missingRequired) . '. Установи их и обнови страницу.');
        } elseif ($missingOptional !== []) {
            $checks[] = InstallCheck::warn('extensions', 'Расширения PHP', 'Всё нужное есть. Необязательные: ' . implode(', ', $missingOptional) . '.');
        } else {
            $checks[] = InstallCheck::ok('extensions', 'Расширения PHP', 'Всё нужное загружено');
        }

        $storage = $this->ensureStorage();
        $checks[] = $storage
            ? InstallCheck::ok('storage', 'Папка для данных', 'Каталог var доступен для записи')
            : InstallCheck::fail('storage', 'Папка для данных', 'Нет доступа к var. Разреши запись пользователю ' . $this->processUser() . ' и обнови страницу.');

        if (is_link($this->storageRoot . '/.env')) {
            $checks[] = InstallCheck::fail('env', 'Файл настроек', '.env не должен быть символической ссылкой — замени его обычным файлом.');
        } else {
            $envWritable = is_file($this->storageRoot . '/.env')
                ? is_writable($this->storageRoot . '/.env')
                : is_writable($this->storageRoot);
            $checks[] = $envWritable
                ? InstallCheck::ok('env', 'Файл настроек', 'Каталог открыт — .env запишется')
                : InstallCheck::fail('env', 'Файл настроек', 'Нельзя записать .env. Проверь права на каталог установки.');
        }

        $free = @disk_free_space($this->storageRoot);
        if ($free === false) {
            $checks[] = InstallCheck::warn('disk', 'Свободное место', 'Не удалось определить — просто проверь, что диск не полный.');
        } elseif ($free < self::MIN_FREE_BYTES) {
            $checks[] = InstallCheck::warn('disk', 'Свободное место', $this->bytes($free) . ' — лучше освободить немного места.');
        } else {
            $checks[] = InstallCheck::ok('disk', 'Свободное место', $this->bytes($free) . ' свободно');
        }

        $checks[] = $this->processCheck();
        $checks[] = $this->documentRootCheck();
        $checks[] = $this->agentCheck();

        return $checks;
    }

    /**
     * Ошибки ввода по полям формы.
     *
     * @return array<string, string>
     */
    public function validate(InstallRequest $request): array
    {
        return array_merge($this->validateConnection($request), $this->validateAdmin($request));
    }

    /**
     * Поля подключения: шаг «База и кэш».
     *
     * @return array<string, string>
     */
    public function validateConnection(InstallRequest $request): array
    {
        return $this->validationErrors($this->validator()->make(
            $request->toArray(),
            InstallRules::connection($request),
            InstallRules::messages(),
            InstallRules::attributes()
        ));
    }

    /**
     * Поля аккаунта: шаг «Хозяин дома».
     *
     * Подтверждение пароля проверяется только из формы: CLI не спрашивает
     * его второй раз, поэтому правило добавляется при наличии значения.
     *
     * @return array<string, string>
     */
    public function validateAdmin(InstallRequest $request, ?string $confirmation = null): array
    {
        $rules = InstallRules::admin();
        $data = $request->toArray();
        if ($confirmation !== null) {
            $rules['admin_password_confirm'] = ['required', 'string', 'same:admin_password'];
            $data['admin_password_confirm'] = $confirmation;
        }

        return $this->validationErrors($this->validator()->make(
            $data,
            $rules,
            InstallRules::messages(),
            InstallRules::attributes()
        ));
    }

    private function validator(): ValidationFactory
    {
        return $this->app->container()->make(ValidationFactory::class);
    }

    /**
     * @return array<string, string>
     */
    private function validationErrors(Validator $validator): array
    {
        if (!$validator->fails()) {
            return [];
        }
        $errors = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            $errors[(string)$field] = (string)($messages[0] ?? 'Проверь поле.');
        }

        return $errors;
    }

    /**
     * Проверка подключений перед применением: база и, если выбрана, Redis.
     *
     * @return list<InstallCheck>
     */
    public function preflight(InstallRequest $request): array
    {
        $checks = $this->checkDatabase($request);
        if ($request->cacheDriver === 'redis') {
            $checks[] = $this->checkRedis($request);
        }

        return $checks;
    }

    /**
     * Применяет установку. Проверки preflight должны быть выполнены заранее.
     */
    public function apply(InstallRequest $request): InstallReport
    {
        $errors = $this->validate($request);
        if ($errors !== []) {
            $field = (string)array_key_first($errors);
            throw new InstallException($errors[$field], $field);
        }
        if ($this->installed()) {
            throw new InstallException('LaIOT уже установлена.');
        }
        if (!$this->ensureStorage()) {
            throw new InstallException('Нет прав на запись в каталог var.', 'storage');
        }

        /** @var DatabaseManager $database */
        $database = ($this->databaseFactory)($request->databaseConfig());
        if (!$database->isConfigured() || !$database->ping()) {
            throw new InstallException(
                'Не получилось подключиться к базе. Проверь хост, имя базы и пароль.',
                'db_host'
            );
        }

        $this->writeEnvironment($request);

        try {
            $migrated = (new Migrator($database, $this->migrationsPath()))->migrate();

            /** @var Application $app */
            $app = ($this->applicationFactory)([
                'database' => $request->databaseConfig(),
                'cache' => $request->cacheConfig($this->app->root()),
            ]);
            $app->boot();

            $adminId = $this->createAdmin($app, $request);
        } catch (InstallException $error) {
            throw $error;
        } catch (Throwable $error) {
            // .env уже записан: повторная попытка продолжит с той же базой.
            throw new InstallException('Установка остановилась: ' . $error->getMessage());
        }

        $installedAt = date('Y-m-d H:i:s');
        $warnings = $this->markInstalled($request, $installedAt, count($migrated));

        return new InstallReport(
            version: $this->version(),
            adminId: $adminId,
            adminLogin: $request->adminLogin,
            migrations: $migrated,
            installedAt: $installedAt,
            cacheDriver: $request->cacheDriver,
            warnings: $warnings
        );
    }

    /**
     * @return list<InstallCheck>
     */
    private function checkDatabase(InstallRequest $request): array
    {
        $checks = [];
        try {
            $server = $this->connect($request, false);
        } catch (Throwable $error) {
            return [InstallCheck::fail('db_server', 'Сервер базы', 'Не получилось подключиться: ' . $this->message($error) . '. Проверь сервер, логин и пароль.')];
        }

        try {
            $version = (string)$server->query('SELECT VERSION()')->fetchColumn();
            $checks[] = InstallCheck::ok('db_server', 'Сервер базы', $request->dbHost . ':' . $request->dbPort . ' · ' . $version);
        } catch (Throwable $error) {
            return [InstallCheck::fail('db_server', 'Сервер базы', $this->message($error))];
        }

        try {
            $statement = $server->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $statement->execute([$request->dbName]);
            $exists = $statement->fetchColumn() !== false;
        } catch (Throwable $error) {
            return array_merge($checks, [
                InstallCheck::fail('db_name', 'База данных', $this->message($error)),
            ]);
        }

        if (!$exists) {
            if (!$request->dbCreate) {
                return array_merge($checks, [
                    InstallCheck::fail('db_name', 'База данных', 'Базы «' . $request->dbName . '» нет. Создай её в FastPanel или включи создание ниже.'),
                ]);
            }
            try {
                $server->exec(
                    'CREATE DATABASE `' . str_replace('`', '``', $request->dbName)
                    . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
            } catch (Throwable $error) {
                return array_merge($checks, [
                    InstallCheck::fail('db_name', 'База данных', 'Не получилось создать базу: ' . $this->message($error)),
                ]);
            }
            $checks[] = InstallCheck::ok('db_name', 'База данных', 'База «' . $request->dbName . '» создана — можно продолжать');
        } else {
            $checks[] = InstallCheck::ok('db_name', 'База данных', 'База «' . $request->dbName . '» на месте');
        }

        try {
            $database = $this->connect($request, true);
            $tables = $database->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $tables = array_map('strval', $tables);
            if (in_array('users', $tables, true)) {
                $admins = (int)$database
                    ->query('SELECT COUNT(*) FROM users WHERE is_admin = 1 AND deleted_at IS NULL')
                    ->fetchColumn();
                if ($admins > 0) {
                    $checks[] = InstallCheck::fail(
                        'db_content',
                        'Содержимое базы',
                        'В этой базе уже есть администратор. Похоже, LaIOT уже стоит — войди в панель или укажи другую базу.'
                    );

                    return $checks;
                }
                if (!in_array('migrations', $tables, true)) {
                    // Таблица users без журнала миграций — чужая схема;
                    // молча дописывать в неё таблицы LaIOT нельзя.
                    $checks[] = InstallCheck::fail(
                        'db_content',
                        'Содержимое базы',
                        'В базе есть таблица users, но нет журнала миграций LaIOT. Укажи другую базу.'
                    );

                    return $checks;
                }
            }
            if (in_array('migrations', $tables, true)) {
                $checks[] = InstallCheck::ok('db_content', 'Содержимое базы', 'Нашёл следы прошлой попытки — продолжим с того же места');
            } elseif ($tables !== []) {
                $checks[] = InstallCheck::warn('db_content', 'Содержимое базы', 'В базе есть другие таблицы (' . count($tables) . '). LaIOT добавит свои, но лучше проверить, что база свободна.');
            } else {
                $checks[] = InstallCheck::ok('db_content', 'Содержимое базы', 'База пуста');
            }
        } catch (Throwable $error) {
            $checks[] = InstallCheck::fail('db_content', 'Содержимое базы', $this->message($error));
        }

        return $checks;
    }

    private function checkRedis(InstallRequest $request): InstallCheck
    {
        try {
            $client = $this->redis($request);
            $pong = (string)$client->ping();
            if (strtoupper($pong) !== 'PONG') {
                return InstallCheck::fail('redis', 'Redis', 'Странный ответ: ' . $pong . '. Проверь, что это действительно Redis.');
            }
        } catch (Throwable $error) {
            return InstallCheck::fail('redis', 'Redis', 'Не отвечает: ' . $this->message($error) . '. Проверь адрес и пароль или выбери файловый кэш.');
        }

        $policy = null;
        $maxmemory = null;
        try {
            $memory = $client->config('GET', 'maxmemory');
            $maxmemory = is_array($memory) ? (int)($memory['maxmemory'] ?? 0) : 0;
            $setting = $client->config('GET', 'maxmemory-policy');
            $policy = is_array($setting) ? (string)($setting['maxmemory-policy'] ?? '') : '';
        } catch (Throwable) {
            // CONFIG может быть отключён на сервере; PING уже доказал доступность.
        }

        if ($policy !== null && $policy !== '' && $policy !== 'noeviction' && (int)$maxmemory > 0) {
            return InstallCheck::warn(
                'redis',
                'Redis',
                'Подключение есть, но политика ' . $policy . ' вытесняет ключи — блокировки фоновых задач могут пропасть. Лучше noeviction.'
            );
        }

        return InstallCheck::ok('redis', 'Redis', $request->redisHost . ':' . $request->redisPort . ', база ' . $request->redisDatabase . ' — отвечает');
    }

    private function processCheck(): InstallCheck
    {
        $name = $this->processUser();
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return InstallCheck::warn('process', 'Пользователь PHP', $name . ' (root) — лучше запускать сайт под отдельным пользователем.');
        }

        return InstallCheck::ok('process', 'Пользователь PHP', $name . ' — всё в порядке');
    }

    /**
     * Веб-корень должен указывать на public: иначе `.env` и `var` могут
     * отдаваться как статика. Проверка возможна только из веб-запроса.
     */
    private function documentRootCheck(): InstallCheck
    {
        $documentRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (PHP_SAPI === 'cli') {
            return InstallCheck::ok('document_root', 'Корень сайта', 'проверяется из браузера');
        }
        if ($documentRoot === '') {
            return InstallCheck::warn('document_root', 'Корень сайта', 'Сервер не передал DOCUMENT_ROOT. Проверь, что корень сайта — public.');
        }

        $expected = realpath($this->app->root() . '/public');
        $actual = realpath($documentRoot);
        if ($expected !== false && $actual !== false && $expected === $actual) {
            return InstallCheck::ok('document_root', 'Корень сайта', $documentRoot . ' — сайт смотрит в public');
        }

        return InstallCheck::warn(
            'document_root',
            'Корень сайта',
            'Сайт отдаёт ' . $documentRoot . ', а не public. Закрой .env, var и vendor в nginx.'
        );
    }

    private function agentCheck(): InstallCheck
    {
        $path = self::AGENT_SOCKET;
        if (!file_exists($path)) {
            return InstallCheck::warn(
                'agent',
                'Фоновые службы',
                'Агент не найден. Установка пройдёт, а службы запустим вручную после установки.'
            );
        }
        try {
            (new SystemdAgentClient($path, 2.0))->coreStatus('queue');

            return InstallCheck::ok('agent', 'Фоновые службы', 'Агент отвечает — службами можно управлять из панели');
        } catch (Throwable $error) {
            return InstallCheck::warn('agent', 'Фоновые службы', 'Агент не отвечает: ' . $error->getMessage());
        }
    }

    private function writeEnvironment(InstallRequest $request): void
    {
        $changes = [
            'APP_TIMEZONE' => $request->timezone,
            'APP_DEBUG' => '0',
            'DB_HOST' => $request->dbHost,
            'DB_PORT' => (string)$request->dbPort,
            'DB_NAME' => $request->dbName,
            'DB_USER' => $request->dbUser,
            'DB_PASSWORD' => $request->dbPassword,
            'CACHE_DRIVER' => $request->cacheDriver,
        ];
        if ($request->cacheDriver === 'redis') {
            $changes['REDIS_HOST'] = $request->redisHost;
            $changes['REDIS_PORT'] = (string)$request->redisPort;
            $changes['REDIS_DB'] = (string)$request->redisDatabase;
            $changes['REDIS_PASSWORD'] = $request->redisPassword;
        }

        try {
            $file = new EnvFile($this->storageRoot . '/.env');
            $contents = $file->contents();
            $environmentKey = getenv('APP_KEY');
            $key = $environmentKey !== false && $environmentKey !== ''
                ? $environmentKey : (Config::parse($contents)['APP_KEY'] ?? '');
            if ($key === '') {
                $changes['APP_KEY'] = EncryptionKey::generate();
            } else {
                // Повтор установки после сбоя не меняет ключ уже записанных данных.
                EncryptionKey::decode($key);
            }
            $file->save($changes, hash('sha256', $contents));
        } catch (Throwable $error) {
            throw new InstallException(
                'Не получилось сохранить настройки в .env. Проверь, что пользователю сайта разрешена запись в каталог установки. '
                . 'Подробности: ' . $error->getMessage()
            );
        }
    }

    private function createAdmin(Application $app, InstallRequest $request): int
    {
        $connection = $app->database()->connection();
        $existing = $connection->table('users')
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($request->adminLogin)])
            ->whereNull('deleted_at')
            ->first();
        if ($existing !== null) {
            if ((int)$existing->is_admin === 1) {
                // Повторный запуск после сбоя между созданием админа и lock.
                return (int)$existing->id;
            }
            throw new InstallException('Логин занят пользователем без прав администратора.', 'admin_login');
        }

        try {
            return $app->container()->make(UserRepository::class)->create(
                $request->adminLogin,
                $request->adminName,
                $request->adminPassword,
                true
            );
        } catch (InvalidArgumentException $error) {
            throw new InstallException($error->getMessage(), 'admin_login');
        }
    }

    /**
     * @return list<string>
     */
    private function markInstalled(InstallRequest $request, string $installedAt, int $migrations): array
    {
        $warnings = [];
        $payload = json_encode([
            'version' => $this->version(),
            'installed_at' => $installedAt,
            'admin' => $request->adminLogin,
            'cache' => $request->cacheDriver,
            'migrations' => $migrations,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $path = $this->lockPath();
        $temporary = $path . '.tmp';
        if ($payload === false
            || file_put_contents($temporary, $payload) === false
            || !rename($temporary, $path)) {
            @unlink($temporary);
            $warnings[] = 'Не удалось записать ' . $path . ': установка завершена, признак установки определяется по базе.';
        } else {
            @chmod($path, 0644);
        }

        if (is_file($this->keyPath())) {
            @unlink($this->keyPath());
        }
        $connectionPath = $this->storageRoot . '/var/install-connection.json';
        if (is_file($connectionPath) && !unlink($connectionPath)) {
            $warnings[] = 'Не удалось удалить временные настройки подключения: ' . $connectionPath;
        }

        return $warnings;
    }

    private function ensureStorage(): bool
    {
        $directory = $this->storageRoot . '/var';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        return is_writable($directory);
    }

    private function migrationsPath(): string
    {
        return $this->migrationsPath ?? $this->app->root() . '/database/migrations';
    }

    private function connect(InstallRequest $request, bool $withDatabase): PDO
    {
        $dsn = 'mysql:host=' . $request->dbHost . ';port=' . $request->dbPort . ';charset=utf8mb4';
        if ($withDatabase) {
            $dsn .= ';dbname=' . $request->dbName;
        }

        return new PDO($dsn, $request->dbUser, $request->dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function redis(InstallRequest $request): RedisClient
    {
        return new RedisClient([
            'scheme' => 'tcp',
            'host' => $request->redisHost,
            'port' => $request->redisPort,
            'database' => $request->redisDatabase,
            'password' => $request->redisPassword === '' ? null : $request->redisPassword,
            'timeout' => 2.0,
        ]);
    }

    private function processUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info)) {
                return $info['name'];
            }
        }

        return get_current_user();
    }

    private function message(Throwable $error): string
    {
        if ($error instanceof PDOException) {
            // DSN и пароль в текст не попадают; SQLSTATE оставляем для поиска.
            return trim(preg_replace('/\s+/', ' ', $error->getMessage()) ?? '');
        }

        return trim($error->getMessage());
    }

    private function bytes(float $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 1) . ' ' . $units[$index];
    }
}
