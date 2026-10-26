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
        $limit = max(1, min(50, $limit));
        $classes = [];
        foreach ($this->classes->all() as $class) {
            $classes[(int)$class['id']] = (string)$class['title'];
        }

        $items = [];
        foreach ($this->objects->all($query) as $object) {
            if (count($items) >= $limit) {
                break;
            }
            $items[] = [
                'title' => (string)$object['title'],
                'class' => $classes[(int)$object['class_id']] ?? '',
                'description' => $object['description'],
            ];
        }

        return $items;
    }

    /**
     * @return array{object:string,class:string,properties:list<array{title:string,data_type:string}>,methods:list<array{title:string,description:string}>}|null
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
            'object' => (string)$object['title'],
            'class' => (string)($class['title'] ?? ''),
            'properties' => $properties,
            'methods' => $methods,
        ];
    }
}
