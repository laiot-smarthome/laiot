<?php

declare(strict_types=1);

namespace MajorDoMo\Methods;

use InvalidArgumentException;
use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Database\DatabaseManager;
use MajorDoMo\Runtime\InvalidUserCode;
use MajorDoMo\Runtime\PhpSyntaxValidator;

/**
 * Методы классов и объектов MyDoMo.
 *
 * 2026-10-27 — код метода проверяется синтаксически до сохранения.
 * 2026-10-27 — методы находятся по цепочке наследования классов; добавлены
 *              объектные методы (`object_id`), переопределяющие метод класса у одного
 *              объекта. Вызов из сценария и OnChange идёт через этот же поиск.
 */
final readonly class MethodRepository
{
    public function __construct(
        private DatabaseManager $database,
        private ClassRepository $classes,
        private PhpSyntaxValidator $syntax
    ) {
    }

    /**
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string}>
     */
    public function all(string $search = '', int $classId = 0): array
    {
        $query = $this->database->connection()->table('methods')->whereNull('deleted_at');
        if ($classId !== 0) {
            $query->where('class_id', $classId);
        }
        if ($search !== '') {
            $query->where(static function ($query) use ($search): void {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        return $query->orderBy('class_id')
            ->orderBy('object_id')
            ->orderBy('title')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int)$row->id,
                'class_id' => (int)$row->class_id,
                'object_id' => (int)($row->object_id ?? 0),
                'title' => (string)$row->title,
                'description' => (string)($row->description ?? ''),
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,origin:string,owner_class_id:int,owner_class_title:string}>
     */
    public function ownForClass(int $classId): array
    {
        $class = $this->classes->find($classId);
        if ($class === null) {
            return [];
        }

        return $this->database->connection()->table('methods')
            ->where('class_id', $classId)
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get()
            ->map(fn (object $row): array => $this->hydrate($row) + [
                'origin' => 'own',
                'owner_class_id' => $classId,
                'owner_class_title' => (string)$class['title'],
            ])
            ->all();
    }

    /**
     * Действующие методы класса: свои и унаследованные; ближайший класс
     * побеждает при совпадении названия.
     *
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,origin:string,owner_class_id:int,owner_class_title:string}>
     */
    public function effectiveForClass(int $classId): array
    {
        $classes = $this->classes->all();
        if ($classes === []) {
            return [];
        }
        $byId = [];
        foreach ($classes as $class) {
            $byId[$class['id']] = $class;
        }
        $lineage = $this->classes->lineageFrom($byId, $classId);
        if ($lineage === []) {
            return [];
        }

        $ids = [];
        foreach ($lineage as $class) {
            $ids[] = (int)$class['id'];
        }
        $rows = $this->database->connection()->table('methods')
            ->whereIn('class_id', $ids)
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get();

        $byClass = [];
        foreach ($rows as $row) {
            $byClass[(int)$row->class_id][] = $row;
        }

        $known = [];
        $result = [];
        foreach ($lineage as $owner) {
            $ownerId = (int)$owner['id'];
            foreach ($byClass[$ownerId] ?? [] as $row) {
                $title = (string)$row->title;
                if (isset($known[$title])) {
                    continue;
                }
                $known[$title] = true;
                $result[] = $this->hydrate($row) + [
                    'origin' => $ownerId === $classId ? 'own' : 'inherited',
                    'owner_class_id' => $ownerId,
                    'owner_class_title' => (string)$owner['title'],
                ];
            }
        }

        usort($result, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        return $result;
    }

    /**
     * Действующие методы объекта: объектный метод, затем класс и предки.
     *
     * @return list<array{id:int,class_id:int,object_id:int,title:string,description:string,origin:string,owner_class_id:int,owner_class_title:string}>
     */
    public function forObject(int $objectId): array
    {
        $classId = $this->objectClassId($objectId);
        if ($classId === null) {
            return [];
        }

        $known = [];
        $result = [];
        foreach ($this->database->connection()->table('methods')
            ->where('object_id', $objectId)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get() as $row) {
            $title = (string)$row->title;
            $known[$title] = true;
            $result[] = $this->hydrate($row) + [
                'origin' => 'object',
                'owner_class_id' => $classId,
                'owner_class_title' => $this->className($classId),
            ];
        }

        foreach ($this->effectiveForClass($classId) as $method) {
            if (isset($known[$method['title']])) {
                continue;
            }
            $known[$method['title']] = true;
            $result[] = $method;
        }

        usort($result, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

        return $result;
    }

    /**
     * @return array<int,int>
     */
    public function countsByClass(): array
    {
        $counts = [];
        $rows = $this->database->connection()->table('methods')
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->select('class_id')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('class_id')
            ->get();
        foreach ($rows as $row) {
            $counts[(int)$row->class_id] = (int)$row->aggregate;
        }

        return $counts;
    }

    /**
     * @param list<array{id:int,title:string,parent_id:int}> $classes
     * @return array<int,array{own:int,inherited:int,effective:int}>
     */
    public function effectiveCounts(array $classes): array
    {
        $byClass = [];
        foreach ($this->database->connection()->table('methods')
            ->where('object_id', 0)
            ->whereNull('deleted_at')
            ->get(['class_id', 'title']) as $row) {
            $byClass[(int)$row->class_id][] = (string)$row->title;
        }

        $byId = [];
        foreach ($classes as $class) {
            $byId[$class['id']] = $class;
        }

        $counts = [];
        foreach ($classes as $class) {
            $known = [];
            $effective = 0;
            foreach ($this->classes->lineageFrom($byId, (int)$class['id']) as $owner) {
                foreach ($byClass[(int)$owner['id']] ?? [] as $title) {
                    if (isset($known[$title])) {
                        continue;
                    }
                    $known[$title] = true;
                    $effective++;
                }
            }
            $own = count($byClass[(int)$class['id']] ?? []);
            $counts[(int)$class['id']] = [
                'own' => $own,
                'inherited' => max(0, $effective - $own),
                'effective' => $effective,
            ];
        }

        return $counts;
    }

    /**
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,code:string}|null
     */
    public function find(int $id): ?array
    {
        $row = $this->database->connection()->table('methods')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : [
            ...$this->hydrate($row),
            'code' => (string)($row->code ?? ''),
        ];
    }

    /**
     * Метод класса с учётом наследования (ближайший класс первым).
     *
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,code:string}|null
     */
    public function findForClass(int $classId, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        foreach ($this->classes->lineage($classId) as $class) {
            $row = $this->database->connection()->table('methods')
                ->where('class_id', (int)$class['id'])
                ->where('object_id', 0)
                ->where('title', $title)
                ->whereNull('deleted_at')
                ->first();
            if ($row !== null) {
                return [
                    ...$this->hydrate($row),
                    'code' => (string)($row->code ?? ''),
                ];
            }
        }

        return null;
    }

    /**
     * Метод объекта: объектный, затем ближайший класс и предки.
     *
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string,code:string}|null
     */
    public function findForObject(int $objectId, int $classId, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $row = $this->database->connection()->table('methods')
            ->where('object_id', $objectId)
            ->where('title', $title)
            ->whereNull('deleted_at')
            ->first();
        if ($row !== null) {
            return [
                ...$this->hydrate($row),
                'code' => (string)($row->code ?? ''),
            ];
        }

        return $this->findForClass($classId, $title);
    }

    public function create(
        int $classId,
        string $title,
        string $description,
        string $code,
        int $objectId = 0
    ): int {
        [$classId, $objectId] = $this->scope($classId, $objectId);
        $title = $this->title($title);
        $this->assertTitleFree($classId, $objectId, $title);
        $this->assertCode($code);
        $now = date('Y-m-d H:i:s');

        return (int)$this->database->connection()->table('methods')->insertGetId([
            'class_id' => $classId,
            'object_id' => $objectId,
            'title' => $title,
            'description' => $description,
            'code' => $code,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function update(
        int $id,
        int $classId,
        string $title,
        string $description,
        string $code,
        int $objectId = 0
    ): void {
        [$classId, $objectId] = $this->scope($classId, $objectId);
        $title = $this->title($title);
        $this->assertTitleFree($classId, $objectId, $title, $id);
        $this->assertCode($code);

        $this->database->connection()->table('methods')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'class_id' => $classId,
                'object_id' => $objectId,
                'title' => $title,
                'description' => $description,
                'code' => $code,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function delete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->database->connection()->table('methods')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    private function objectClassId(int $objectId): ?int
    {
        $row = $this->database->connection()->table('objects')
            ->where('id', $objectId)
            ->whereNull('deleted_at')
            ->first(['class_id']);

        return $row === null ? null : (int)$row->class_id;
    }

    private function className(int $classId): string
    {
        return (string)($this->classes->find($classId)['title'] ?? '');
    }

    /**
     * @return array{0:int,1:int} [class_id, object_id]
     */
    private function scope(int $classId, int $objectId): array
    {
        $objectId = max(0, $objectId);
        if ($objectId > 0) {
            $object = $this->database->connection()->table('objects')
                ->where('id', $objectId)
                ->whereNull('deleted_at')
                ->first(['class_id']);
            if ($object === null) {
                throw new InvalidArgumentException('Объект для метода не найден.');
            }
            $objectClassId = (int)$object->class_id;
            if ($classId > 0 && $classId !== $objectClassId) {
                throw new InvalidArgumentException('Класс не совпадает с классом объекта.');
            }
            $classId = $objectClassId;
        }

        if ($classId > 0 && $this->classes->find($classId) === null) {
            throw new InvalidArgumentException('Класс метода не найден.');
        }
        if ($classId <= 0 && $objectId <= 0) {
            throw new InvalidArgumentException('Метод должен принадлежать классу или объекту.');
        }

        return [$classId, $objectId];
    }

    private function assertTitleFree(int $classId, int $objectId, string $title, int $exceptId = 0): void
    {
        $query = $this->database->connection()->table('methods')
            ->where('class_id', $classId)
            ->where('object_id', $objectId)
            ->where('title', $title);
        if ($exceptId > 0) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('Метод с таким названием уже есть в этой области.');
        }
    }

    /**
     * Единственная точка сохранения кода: invalid PHP не попадает в базу.
     */
    private function assertCode(string $code): void
    {
        $error = $this->syntax->validate($code);
        if ($error === null) {
            return;
        }

        throw new InvalidUserCode($error['line'], $error['message']);
    }

    /**
     * @return array{id:int,class_id:int,object_id:int,title:string,description:string}
     */
    private function hydrate(object $row): array
    {
        return [
            'id' => (int)$row->id,
            'class_id' => (int)$row->class_id,
            'object_id' => (int)($row->object_id ?? 0),
            'title' => (string)$row->title,
            'description' => (string)($row->description ?? ''),
        ];
    }

    private function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 191) {
            throw new InvalidArgumentException('Название метода обязательно, до 191 символа.');
        }

        return $title;
    }
}
