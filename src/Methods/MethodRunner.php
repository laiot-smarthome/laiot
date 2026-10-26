<?php

declare(strict_types=1);

namespace MajorDoMo\Methods;

use InvalidArgumentException;
use MajorDoMo\Objects\ObjectRepository;

/**
 * Выполнение пользовательского метода.
 *
 * Код методов — доверенный пользовательский PHP (как в alpha). Он выполняется
 * через временный файл, без eval; файл удаляется в finally.
 *
 * 2026-10-27 — метод ищется у объекта, затем в классе и по цепочке наследования.
 */
final readonly class MethodRunner
{
    public function __construct(
        private ObjectRepository $objects,
        private MethodRepository $methods
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function run(string $objectTitle, string $methodTitle, array $params = []): mixed
    {
        $object = $this->objects->findByTitle(trim($objectTitle));
        if ($object === null) {
            throw new InvalidArgumentException('Объект не найден: ' . $objectTitle);
        }
        $method = $this->methods->findForObject((int)$object['id'], (int)$object['class_id'], trim($methodTitle));
        if ($method === null) {
            throw new InvalidArgumentException('Метод не найден: ' . $methodTitle);
        }
        if (trim($method['code']) === '') {
            return null;
        }

        $file = tempnam(sys_get_temp_dir(), 'mydomo_method_');
        if ($file === false) {
            throw new InvalidArgumentException('Не удалось создать временный файл метода.');
        }
        try {
            file_put_contents($file, "<?php\n" . $method['code']);
            $runner = static function (string $__file, array $params): mixed {
                return include $__file;
            };

            return $runner($file, $params);
        } finally {
            @unlink($file);
        }
    }
}
