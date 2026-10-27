<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use MajorDoMo\Foundation\Config;
use PHPUnit\Framework\TestCase;

/**
 * Разбор `.env` без alpha Config: кавычки, комментарии, типы и приоритет
 * переменной процесса над файлом.
 *
 * 2026-10-27 — тест создан вместе с Foundation\Config.
 */
final class ConfigTest extends TestCase
{
    private string $directory = '';

    public function testBootParsesQuotesAndComments(): void
    {
        $this->bootFile(<<<'ENV'
# comment
APP_DEBUG="true"
DB_NAME='my domo'
DB_PORT=3307
EMPTY=
export APP_TIMEZONE=Europe/Moscow
ENV);

        self::assertTrue(Config::bool('APP_DEBUG'));
        self::assertSame('my domo', Config::string('DB_NAME'));
        self::assertSame(3307, Config::int('DB_PORT'));
        self::assertSame('', Config::string('EMPTY', 'fallback'));
        self::assertSame('Europe/Moscow', Config::string('APP_TIMEZONE'));
        self::assertSame('fallback', Config::string('UNKNOWN', 'fallback'));
    }

    public function testProcessEnvironmentOverridesFile(): void
    {
        $this->bootFile("APP_DEBUG=0\nDB_NAME=file\n");
        putenv('APP_DEBUG=1');
        putenv('DB_NAME=process');

        try {
            self::assertTrue(Config::bool('APP_DEBUG'));
            self::assertSame('process', Config::string('DB_NAME'));
        } finally {
            putenv('APP_DEBUG');
            putenv('DB_NAME');
        }
    }

    private function bootFile(string $contents): void
    {
        $this->directory = sys_get_temp_dir() . '/mydomo_config_' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0775, true);
        file_put_contents($this->directory . '/.env', $contents);
        Config::boot($this->directory);
    }

    protected function tearDown(): void
    {
        if ($this->directory !== '' && is_dir($this->directory)) {
            @unlink($this->directory . '/.env');
            @rmdir($this->directory);
        }
        putenv('APP_DEBUG');
        putenv('DB_NAME');
    }
}
