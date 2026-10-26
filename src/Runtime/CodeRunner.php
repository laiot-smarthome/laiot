<?php

declare(strict_types=1);

namespace MajorDoMo\Runtime;

use RuntimeException;

/**
 * Выполнение доверенного пользовательского PHP через временный файл.
 * Без eval; файл удаляется в finally.
 */
final class CodeRunner
{
    /**
     * @param array<string, mixed> $params
     */
    public function run(string $code, array $params = []): mixed
    {
        if (trim($code) === '') {
            return null;
        }

        $file = tempnam(sys_get_temp_dir(), 'mydomo_code_');
        if ($file === false) {
            throw new RuntimeException('Не удалось создать временный файл.');
        }
        try {
            file_put_contents($file, "<?php\n" . $code);
            $runner = static function (string $__file, array $params): mixed {
                return include $__file;
            };

            return $runner($file, $params);
        } finally {
            @unlink($file);
        }
    }
}
