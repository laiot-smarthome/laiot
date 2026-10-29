<?php

declare(strict_types=1);

namespace LaIOT\Services\Install;

use Illuminate\Validation\Rule;

/**
 * Правила валидатора для мастера установки.
 *
 * Собраны в одном месте, чтобы шаги подключения и аккаунта, CLI и тесты
 * проверяли одинаковые поля штатными правилами Illuminate. Предметным
 * остаётся только пароль — см. StrongPassword.
 *
 * 2026-10-28 — установщик переведён на общий Illuminate-валидатор.
 */
final class InstallRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function connection(InstallRequest $request): array
    {
        $databaseName = $request->dbCreate
            ? ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/D']
            : ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/D'];

        return [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_name' => $databaseName,
            'db_user' => ['required', 'string', 'max:128'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'db_create' => ['boolean'],
            'cache_driver' => ['required', Rule::in(['redis', 'file'])],
            'redis_host' => ['required_if:cache_driver,redis', 'nullable', 'string', 'max:255'],
            'redis_port' => ['required_if:cache_driver,redis', 'nullable', 'integer', 'between:1,65535'],
            'redis_database' => ['required_if:cache_driver,redis', 'nullable', 'integer', 'between:0,15'],
            'redis_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function admin(): array
    {
        return [
            'admin_login' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]{7,63}$/D'],
            'admin_name' => ['required', 'string', 'max:191'],
            'admin_password' => ['required', 'string', new StrongPassword()],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'db_host.required' => 'Укажи сервер базы — обычно это 127.0.0.1.',
            'db_port.required' => 'Укажи порт базы, обычно 3306.',
            'db_port.integer' => 'Порт базы — число.',
            'db_port.between' => 'Порт базы — от 1 до 65535.',
            'db_name.required' => 'Напиши имя базы (до 64 символов).',
            'db_name.regex' => 'Имя базы: латиница, цифры, _ и -.',
            'db_name.max' => 'Имя базы — до 64 символов.',
            'db_user.required' => 'Укажи пользователя базы.',
            'cache_driver.in' => 'Выбери, где хранить кэш: Redis или файлы.',
            'redis_host.required_if' => 'Укажи сервер Redis или выбери файловый кэш.',
            'redis_port.required_if' => 'Укажи порт Redis, обычно 6379.',
            'redis_port.integer' => 'Порт Redis — число.',
            'redis_port.between' => 'Порт Redis — от 1 до 65535.',
            'redis_database.required_if' => 'Укажи номер базы Redis, обычно 0.',
            'redis_database.integer' => 'Номер базы Redis — число.',
            'redis_database.between' => 'Номер базы Redis — от 0 до 15.',
            'admin_login.required' => 'Придумай логин для входа.',
            'admin_login.regex' => 'Логин: 8–64 символа, латиница, цифры и _, первый символ — буква или _.',
            'admin_name.required' => 'Напиши имя — оно будет видно в панели.',
            'admin_name.max' => 'Имя — до 191 символа.',
            'admin_password_confirm.same' => 'Пароли не совпадают.',
            'timezone.in' => 'Такого часового пояса нет — выбери из списка.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'db_host' => 'сервер базы',
            'db_port' => 'порт базы',
            'db_name' => 'имя базы',
            'db_user' => 'пользователь базы',
            'cache_driver' => 'хранилище кэша',
            'redis_host' => 'сервер Redis',
            'redis_port' => 'порт Redis',
            'redis_database' => 'база Redis',
            'admin_login' => 'логин',
            'admin_name' => 'имя',
            'admin_password' => 'пароль',
            'timezone' => 'часовой пояс',
        ];
    }
}
