<?php

declare(strict_types=1);

namespace LaIOT\Services\Runtime;

use ParseError;

/**
 * Проверка синтаксиса пользовательского PHP до сохранения.
 *
 * Код сценариев и методов хранится без открывающего тега: `CodeRunner`
 * добавляет `<?php` сам. Поэтому тот же префикс добавляется здесь, а разбор
 * идёт штатным парсером PHP (`token_get_all` с `TOKEN_PARSE`), без запуска
 * кода и без внешнего процесса. Строка ошибки остаётся строкой исходника:
 * префикс дописан без перевода строки.
 *
 * 2026-10-27 — проверка синтаксиса перед сохранением сценария или метода.
 */
final class PhpSyntaxValidator
{
    /**
     * Возвращает ошибку с номером строки или null, если код разбирается.
     *
     * @return array{line:int,message:string}|null
     */
    public function validate(string $code): ?array
    {
        if (trim($code) === '') {
            // Пустой код допустим: сценарий или метод просто ничего не делает.
            return null;
        }

        if (preg_match('/^\s*<\?/', $code) === 1) {
            return [
                'line' => 1,
                'message' => 'Уберите открывающий тег <?php: ядро добавляет его при запуске само.',
            ];
        }

        try {
            // Результат нужен парсеру: без TOKEN_PARSE invalid-код не даёт
            // ParseError, а токены дальше не используются.
            $tokens = token_get_all('<?php ' . $code, TOKEN_PARSE);
            unset($tokens);

            return null;
        } catch (ParseError $error) {
            return ['line' => $error->getLine(), 'message' => $error->getMessage()];
        }
    }
}
