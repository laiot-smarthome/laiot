<?php

declare(strict_types=1);

namespace LaIOT\Services\Updates;

use PDO;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Один атомарный снимок MySQL на релиз. Отдельные базы сторонних модулей не входят в операцию.
 * 2026-10-29 — дамп и описание в одном zip, сверка ключа и БД до разрушительного восстановления.
 */
class UpdateDatabase
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config, private readonly string $key, private readonly string $backups)
    {
    }

    public function check(): string
    {
        if (($this->config['driver'] ?? '') !== 'mysql') {
            throw new RuntimeException('Обновление рабочей установки поддерживает MySQL / MariaDB.');
        }
        $this->executable('dump');
        $this->executable('client');
        $pdo = $this->pdo();
        $pdo->query('SELECT 1');
        $query = $pdo->prepare('SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        $query->execute([(string)$this->config['database']]);
        $required = (float)$query->fetchColumn() * 6 + 512 * 1024 * 1024;
        $free = disk_free_space(is_dir($this->backups) ? $this->backups : dirname($this->backups));
        if ($free === false || $free < $required) {
            throw new RuntimeException('Недостаточно места для дампа и атомарной замены последнего снимка БД.');
        }
        return 'MySQL отвечает; утилиты резервного копирования доступны.';
    }

    /** @param array<string, mixed> $modules
     * @param list<string> $services
     */
    public function snapshot(string $id, array $modules, array $services = []): void
    {
        ReleaseManifest::assertId($id);
        $this->check();
        UpdateFiles::directory($this->backups);
        $directory = $this->backups . '/.prepare-' . bin2hex(random_bytes(8));
        UpdateFiles::directory($directory);
        $sql = $directory . '/database.sql';
        $output = fopen($sql, 'xb');
        if ($output === false) {
            throw new RuntimeException('Не удалось создать дамп БД.');
        }
        chmod($sql, 0600);
        try {
            $process = $this->process('dump', ['--single-transaction', '--quick', '--routines', '--events', '--triggers', '--hex-blob', '--add-drop-table', '--no-tablespaces']);
            // Поток не накапливается в памяти PHP даже для большой истории значений.
            $process->run(static function (string $type, string $buffer) use ($output, $process): void {
                if ($type === Process::OUT && fwrite($output, $buffer) !== strlen($buffer)) {
                    throw new RuntimeException('Недостаточно места для дампа БД.');
                }
                $process->clearOutput();
                $process->clearErrorOutput();
            });
            if (!$process->isSuccessful() || ftell($output) === 0 || !fflush($output) || !fsync($output)) {
                throw new RuntimeException('Не удалось завершить дамп БД. Проверьте права на routines/events и свободное место.');
            }
        } finally {
            fclose($output);
        }
        $metadata = [
            'release' => $id, 'created_at' => gmdate('c'), 'modules' => $modules, 'services' => $services,
            'database' => $this->identity(), 'key' => hash('sha256', $this->key),
            'sha256' => hash_file('sha256', $sql),
        ];
        $temporary = $directory . '/snapshot.zip';
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true
            || !$zip->addFile($sql, 'database.sql')
            || !$zip->addFromString('snapshot.json', json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
            || !$zip->close()) {
            throw new RuntimeException('Не удалось упаковать снимок БД.');
        }
        chmod($temporary, 0600);
        $this->verify($temporary, $id);
        $stream = fopen($temporary, 'r+b');
        if ($stream === false || !fsync($stream)) {
            throw new RuntimeException('Не удалось синхронизировать снимок БД.');
        }
        fclose($stream);
        if (!rename($temporary, $this->path($id))) {
            throw new RuntimeException('Не удалось заменить снимок БД. Предыдущая копия сохранена.');
        }
        UpdateFiles::syncDirectory($this->backups);
        unlink($sql);
        rmdir($directory);
    }

    /** @return array<string, mixed> */
    public function info(string $id): array
    {
        if (!is_file($this->path($id))) {
            return [];
        }
        return $this->verify($this->path($id), $id);
    }

    public function restore(string $id): void
    {
        $this->verify($this->path($id), $id);
        $zip = new ZipArchive();
        if ($zip->open($this->path($id)) !== true) {
            throw new RuntimeException('Не удалось открыть проверенный снимок.');
        }
        $input = $zip->getStream('database.sql');
        if ($input === false) {
            throw new RuntimeException('Не удалось открыть проверенный дамп.');
        }
        try {
            $pdo = $this->pdo();
            $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
            // Импорт поверх новой схемы оставил бы таблицы отсутствующего в старой версии кода.
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($tables as $table) {
                    $kind = $table[1] === 'VIEW' ? 'VIEW' : 'TABLE';
                    $pdo->exec('DROP ' . $kind . ' `' . str_replace('`', '``', (string)$table[0]) . '`');
                }
                $routines = $pdo->prepare('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?');
                $routines->execute([(string)$this->config['database']]);
                foreach ($routines->fetchAll(PDO::FETCH_ASSOC) as $routine) {
                    $kind = $routine['ROUTINE_TYPE'] === 'PROCEDURE' ? 'PROCEDURE' : 'FUNCTION';
                    $pdo->exec('DROP ' . $kind . ' `' . str_replace('`', '``', (string)$routine['ROUTINE_NAME']) . '`');
                }
                $events = $pdo->prepare('SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?');
                $events->execute([(string)$this->config['database']]);
                foreach ($events->fetchAll(PDO::FETCH_COLUMN) as $event) {
                    $pdo->exec('DROP EVENT `' . str_replace('`', '``', (string)$event) . '`');
                }
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
            $process = $this->process('client', ['--binary-mode']);
            $process->setInput($input);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new RuntimeException('Импорт БД прервался. Снимок сохранён; повторите восстановление.');
            }
        } finally {
            fclose($input);
            $zip->close();
        }
    }

    public function path(string $id): string
    {
        ReleaseManifest::assertId($id);
        return $this->backups . '/' . $id . '.zip';
    }

    private function identity(): string
    {
        return hash('sha256', implode('|', array_map(fn (string $name): string => (string)($this->config[$name] ?? ''), ['host', 'port', 'database', 'username'])));
    }

    /** @return array<string, mixed> */
    private function verify(string $path, string $id): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть снимок БД.');
        }
        $json = $zip->getFromName('snapshot.json');
        $stream = $zip->getStream('database.sql');
        if (!is_string($json) || $stream === false) {
            $zip->close();
            throw new RuntimeException('Снимок БД неполный.');
        }
        try {
            $metadata = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            $checksum = hash_final($hash);
        } finally {
            fclose($stream);
            $zip->close();
        }
        if (!is_array($metadata) || ($metadata['release'] ?? '') !== $id
            || ($metadata['database'] ?? '') !== $this->identity()
            || ($metadata['key'] ?? '') !== hash('sha256', $this->key)
            || !hash_equals((string)($metadata['sha256'] ?? ''), $checksum)) {
            throw new RuntimeException('Снимок повреждён либо относится к другой БД или ключу шифрования.');
        }
        return $metadata;
    }

    private function pdo(): PDO
    {
        return new PDO(
            'mysql:host=' . (string)($this->config['host'] ?? '127.0.0.1')
            . ';port=' . (int)($this->config['port'] ?? 3306) . ';dbname=' . (string)$this->config['database'] . ';charset=utf8mb4',
            (string)$this->config['username'],
            (string)($this->config['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
    }

    private function executable(string $kind): string
    {
        $finder = new ExecutableFinder();
        $path = $kind === 'dump' ? ($finder->find('mariadb-dump') ?? $finder->find('mysqldump')) : ($finder->find('mariadb') ?? $finder->find('mysql'));
        return $path ?? throw new RuntimeException('Не установлен клиент MySQL для резервного копирования.');
    }

    /** @param list<string> $arguments */
    private function process(string $kind, array $arguments): Process
    {
        return new Process(array_merge([
            $this->executable($kind), '--no-defaults', '--protocol=tcp', '--default-character-set=utf8mb4',
            '--host=' . (string)($this->config['host'] ?? '127.0.0.1'), '--port=' . (int)($this->config['port'] ?? 3306),
            '--user=' . (string)$this->config['username'],
        ], $arguments, [(string)$this->config['database']]), null, ['MYSQL_PWD' => (string)($this->config['password'] ?? '')], null, 1800);
    }
}
