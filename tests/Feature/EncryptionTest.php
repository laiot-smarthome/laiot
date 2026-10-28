<?php

declare(strict_types=1);

namespace MajorDoMo\Tests\Feature;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Encryption\MissingAppKeyException;
use MajorDoMo\Database\Model;
use MajorDoMo\Foundation\Application;
use MajorDoMo\Foundation\Config;
use MajorDoMo\Http\Session\ArraySession;
use MajorDoMo\Security\EncryptionKey;
use MajorDoMo\Settings\SettingsSchema;
use PHPUnit\Framework\TestCase;

/**
 * Шифрование проверяется со случайными тестовыми ключами и SQLite в памяти.
 *
 * Изменения:
 * 2026-10-28 — штатные casts, повреждение данных, ротация и изоляция Application.
 */
final class EncryptionTest extends TestCase
{
    public function testNativeCastsPersistCiphertextAndRestoreValues(): void
    {
        $app = $this->application(EncryptionKey::generate());
        $app->database()->schema()->create('encrypted_fixtures', static function (Blueprint $table): void {
            $table->increments('id');
            $table->text('secret')->nullable();
            $table->text('options')->nullable();
            $table->timestamps();
        });
        $encrypter = $app->container()->make(EncrypterContract::class);
        self::assertSame($encrypter, $app->container()->make(Encrypter::class));
        self::assertSame($encrypter, $app->container()->make('encrypter'));
        $item = EncryptedFixture::query()->create([
            'secret' => 'test-token',
            'options' => ['host' => 'localhost', 'enabled' => true],
        ]);
        $raw = $app->database()->connection()->table('encrypted_fixtures')->first();
        self::assertNotNull($raw);
        self::assertNotSame('test-token', $raw->secret);
        self::assertStringNotContainsString('localhost', $raw->options);
        self::assertSame('test-token', $encrypter->decrypt($raw->secret, false));
        $loaded = EncryptedFixture::query()->findOrFail($item->getKey());
        self::assertSame('test-token', $loaded->getAttribute('secret'));
        self::assertSame(['host' => 'localhost', 'enabled' => true], $loaded->getAttribute('options'));
        self::assertArrayNotHasKey('secret', $loaded->toArray());
        $loaded->setAttribute('secret', null);
        $loaded->save();
        self::assertNull($loaded->fresh()?->getAttribute('secret'));
    }

    public function testCorruptedCiphertextIsRejected(): void
    {
        $app = $this->application(EncryptionKey::generate());
        $encrypter = $app->container()->make(Encrypter::class);
        $ciphertext = $encrypter->encryptString('test-only');
        self::assertSame('test-only', $encrypter->decryptString($ciphertext));
        $this->expectException(DecryptException::class);
        $encrypter->decryptString(substr($ciphertext, 0, -8) . 'invalid!');
    }

    public function testModelUsesCurrentApplicationAndPreviousKeys(): void
    {
        $oldKey = EncryptionKey::generate();
        $this->application($oldKey);
        $item = new EncryptedFixture(['secret' => 'old-value']);
        $stored = $item->getAttributes();
        $newKey = EncryptionKey::generate();
        $this->application($newKey);
        $loaded = new EncryptedFixture();
        $loaded->setRawAttributes($stored);
        try {
            $loaded->getAttribute('secret');
            self::fail('Ключ другого Application не должен расшифровывать старое значение.');
        } catch (DecryptException) {
            // Без старого ключа чтение должно завершиться явной ошибкой.
        }
        $app = $this->application($newKey, [$oldKey]);
        self::assertSame('old-value', $loaded->getAttribute('secret'));
        $loaded->setAttribute('secret', 'new-value');
        self::assertSame('new-value', $app->container()->make(Encrypter::class)
            ->decrypt($loaded->getAttributes()['secret'], false));
    }

    public function testMissingKeyDoesNotPreventBootButRejectsEncryption(): void
    {
        $app = $this->application('');
        $this->expectException(MissingAppKeyException::class);
        $app->container()->make(EncrypterContract::class);
    }

    public function testEnvironmentLoadsKeysWithoutMakingThemEditable(): void
    {
        $root = sys_get_temp_dir() . '/mydomo-encryption-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        $key = EncryptionKey::generate();
        $previous = EncryptionKey::generate();
        file_put_contents($root . '/.env', 'APP_KEY="' . $key . '"' . "\n"
            . 'APP_PREVIOUS_KEYS="' . $previous . '"' . "\n");
        $environmentKey = getenv('APP_KEY');
        $environmentPrevious = getenv('APP_PREVIOUS_KEYS');
        putenv('APP_KEY');
        putenv('APP_PREVIOUS_KEYS');
        try {
            Config::boot($root);
            $config = SettingsSchema::environment(dirname(__DIR__, 2));
            self::assertTrue($key === $config['encryption']['key']);
            self::assertTrue([$previous] === $config['encryption']['previous_keys']);
            self::assertArrayNotHasKey('APP_KEY', SettingsSchema::fields());
            self::assertArrayNotHasKey('APP_PREVIOUS_KEYS', SettingsSchema::fields());
            putenv('APP_PREVIOUS_KEYS=0');
            self::assertSame(['0'], SettingsSchema::environment(dirname(__DIR__, 2))['encryption']['previous_keys']);
            $app = $this->application($config['encryption']['key'], $config['encryption']['previous_keys']);
            self::assertSame(32, strlen($app->container()->make(EncrypterContract::class)->getKey()));
        } finally {
            putenv($environmentKey === false ? 'APP_KEY' : 'APP_KEY=' . $environmentKey);
            putenv($environmentPrevious === false ? 'APP_PREVIOUS_KEYS' : 'APP_PREVIOUS_KEYS=' . $environmentPrevious);
            Config::boot($root . '/absent');
            unlink($root . '/.env');
            rmdir($root);
        }
    }

    /** @param list<string> $previous */
    private function application(string $key, array $previous = []): Application
    {
        return (new Application(dirname(__DIR__, 2), [
            'session' => new ArraySession(),
            'cache' => ['driver' => 'array'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'encryption' => ['key' => $key, 'previous_keys' => $previous],
            'log' => 'php://stderr',
        ]))->boot();
    }
}

/** 2026-10-28 — модель фикстуры использует родные encrypted-casts Eloquent. */
final class EncryptedFixture extends Model
{
    protected $table = 'encrypted_fixtures';

    /** @var array<string, string> */
    protected $casts = ['secret' => 'encrypted', 'options' => 'encrypted:array'];

    /** @var list<string> */
    protected $hidden = ['secret', 'options'];
}
