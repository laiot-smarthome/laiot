<?php

declare(strict_types=1);

namespace MajorDoMo\Methods;

use InvalidArgumentException;
use MajorDoMo\Notifications\NotificationService;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyValueService;

/**
 * Выполнение пользовательского метода.
 *
 * Код методов — доверенный пользовательский PHP (как в alpha). Он выполняется
 * через временный файл, без eval; файл удаляется в finally.
 *
 * Код получает `$params` и `$this` — MethodContext объекта: `get()`, `set()`,
 * `history()`, `call()`, `notify()` и короткий доступ к параметрам. Контекст
 * привязан к тому же объекту, чей метод вызван.
 *
 * 2026-10-27 — метод выполняется с контекстом объекта, `$this` больше не пустой.
 * 2026-10-27 — метод ищется у объекта, затем в классе и по цепочке наследования.
 */
final readonly class MethodRunner
{
    public function __construct(
        private ObjectRepository $objects,
        private MethodRepository $methods,
        private PropertyValueService $values,
        private NotificationService $notifications
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

            $context = new MethodContext(
                (string)$object['title'],
                (int)$object['id'],
                trim($methodTitle),
                $params,
                $this->values,
                $this->notifications,
                fn (string $method, array $params = []): mixed => $this->run(
                    (string)$object['title'],
                    $method,
                    $params
                )
            );

            // Замыкание привязано к контексту: include внутри него видит `$this`
            // и `$params`, но не глобалы ядра.
            $runner = function (string $__file) use ($params): mixed {
                return include $__file;
            };

            return $runner->call($context, $file);
        } finally {
            @unlink($file);
        }
    }
}
