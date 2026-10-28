<?php

declare(strict_types=1);

namespace MajorDoMo\LinkedObject;

use MajorDoMo\Classes\ClassRepository;
use MajorDoMo\Methods\MethodRepository;
use MajorDoMo\Objects\ObjectRepository;
use MajorDoMo\Properties\PropertyRepository;

/**
 * Контекст для редактора связей объекта: поиск объектов и свойства/методы.
 *
 * Новый Alpine-компонент `x-md.linked-object` не использует старый
 * `modules/linkedobject` и его JS.
 *
 * 2026-10-28 — начальный список и ссылки для нового редактора связей.
 * 2026-10-27 — контекст объекта включает объектные и унаследованные члены.
 */
final readonly class LinkedObjectService
{
    public function __construct(
        private ObjectRepository $objects,
        private PropertyRepository $properties,
        private MethodRepository $methods,
        private ClassRepository $classes
    ) {
    }

    /**
     * @return list<array{title:string,class:string,description:string}>
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return $this->suggestions($query, $limit);
    }

    /**
     * Начальные варианты доступны и без ввода имени, как в панели alpha.
     *
     * @return list<array{title:string,class:string,description:string}>
     */
    public function suggestions(string $query = '', int $limit = 50): array
    {
        $query = mb_substr(trim($query), 0, 191);
        $limit = max(1, min(50, $limit));
        $classes = [];
        foreach ($this->classes->all() as $class) {
            $classes[(int)$class['id']] = (string)$class['title'];
        }

        $items = [];
        foreach ($this->objects->all() as $object) {
            $classTitle = $classes[(int)$object['class_id']] ?? '';
            if ($query !== '' && !str_contains(
                mb_strtolower(str_replace('ё', 'е', $object['title'] . ' ' . $object['description'] . ' ' . $classTitle)),
                mb_strtolower(str_replace('ё', 'е', $query))
            )) {
                continue;
            }
            if (count($items) >= $limit) {
                break;
            }
            $items[] = [
                'title' => (string)$object['title'],
                'class' => $classTitle,
                'description' => $object['description'],
            ];
        }

        return $items;
    }

    /**
     * @return array{id:int,object:string,class:string,properties:list<array{title:string,data_type:string}>,methods:list<array{title:string,description:string}>}|null
     */
    public function context(string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }
        $object = $this->objects->findByTitle($title);
        if ($object === null) {
            return null;
        }
        $class = $object['class_id'] > 0 ? $this->classes->find((int)$object['class_id']) : null;
        $properties = [];
        foreach ($this->properties->forObject((int)$object['id']) as $property) {
            $properties[] = ['title' => (string)$property['title'], 'data_type' => (string)$property['data_type']];
        }
        $methods = [];
        foreach ($this->methods->forObject((int)$object['id']) as $method) {
            $methods[] = ['title' => (string)$method['title'], 'description' => $method['description']];
        }

        return [
            'id' => (int)$object['id'],
            'object' => (string)$object['title'],
            'class' => (string)($class['title'] ?? ''),
            'properties' => $properties,
            'methods' => $methods,
        ];
    }
}
