<?php

declare(strict_types=1);

namespace LaIOT\Services\Panel;

use LaIOT\Services\Classes\ClassRepository;
use LaIOT\Services\Methods\MethodRepository;
use LaIOT\Services\Objects\ObjectRepository;
use LaIOT\Services\Properties\PropertyRepository;

/**
 * Снимок дерева классов, объектов и счётчиков для панели.
 *
 * 2026-10-29 — сборка каталога вынесена из HTTP-контроллера; ошибки чтения
 * передаются общей диагностике вместо показа ложного пустого состояния.
 */
final readonly class ClassCatalog
{
    public function __construct(
        private ClassRepository $classes,
        private PropertyRepository $properties,
        private MethodRepository $methods,
        private ObjectRepository $objects
    ) {
    }

    /** @return array<string,mixed> */
    public function data(): array
    {
        $allClasses = $this->classes->all();
        $rows = $this->classes->flattenedFrom($allClasses);
        $objectsByClass = $this->objects->groupedByClass();
        $propertyCounts = $this->properties->effectiveCounts($allClasses);
        $methodCounts = $this->methods->effectiveCounts($allClasses);
        $objectCounts = array_map(static fn (array $objects): int => count($objects), $objectsByClass);

        $childrenByParent = [];
        foreach ($allClasses as $class) {
            $parentId = (int)$class['parent_id'];
            if ($parentId > 0) {
                $childrenByParent[$parentId][] = (int)$class['id'];
            }
        }
        $subtreeObjectCounts = [];
        $visiting = [];
        $subtree = function (int $classId) use (
            &$subtree,
            &$subtreeObjectCounts,
            &$visiting,
            $childrenByParent,
            $objectCounts
        ): int {
            if (isset($subtreeObjectCounts[$classId])) {
                return $subtreeObjectCounts[$classId];
            }
            if (isset($visiting[$classId])) {
                return 0;
            }
            $visiting[$classId] = true;
            $total = (int)($objectCounts[$classId] ?? 0);
            foreach ($childrenByParent[$classId] ?? [] as $childId) {
                $total += $subtree($childId);
            }
            unset($visiting[$classId]);

            return $subtreeObjectCounts[$classId] = $total;
        };
        foreach ($allClasses as $class) {
            $subtree((int)$class['id']);
        }

        $treeClasses = [];
        $treeObjects = [];
        foreach ($rows as $row) {
            $classId = (int)$row['id'];
            $classObjects = $objectsByClass[$classId] ?? [];
            $treeClasses[] = [
                'id' => $classId,
                'parent_id' => (int)$row['parent_id'],
                'depth' => (int)$row['depth'],
                'title' => (string)$row['title'],
                'description' => (string)$row['description'],
                'path' => (string)$row['path'],
                'has_children' => (bool)$row['has_children'],
                'children_count' => (int)$row['children_count'],
                'objects_count' => count($classObjects),
                'objects_deep_count' => (int)($subtreeObjectCounts[$classId] ?? 0),
            ];
            $treeObjects[(string)$classId] = array_map(
                static fn (array $object): array => [
                    'id' => (int)$object['id'],
                    'title' => (string)$object['title'],
                    'description' => (string)$object['description'],
                    'location_id' => (int)$object['location_id'],
                    'location_title' => (string)$object['location_title'],
                ],
                $classObjects
            );
        }

        return [
            'rows' => $rows,
            'classes' => $allClasses,
            'objectsByClass' => $objectsByClass,
            'propertyCounts' => $propertyCounts,
            'methodCounts' => $methodCounts,
            'objectCounts' => $objectCounts,
            'subtreeObjectCounts' => $subtreeObjectCounts,
            'tree' => [
                'classes' => $treeClasses,
                'objects' => (object)$treeObjects,
            ],
            'totalObjects' => array_sum($objectCounts),
            'totalProperties' => array_sum(array_column($propertyCounts, 'own')),
            'totalMethods' => array_sum(array_column($methodCounts, 'own')),
            'totalSubclasses' => count(array_filter($allClasses, static fn (array $class): bool => (int)$class['parent_id'] > 0)),
            'rootClasses' => count(array_filter($allClasses, static fn (array $class): bool => (int)$class['parent_id'] === 0)),
        ];
    }
}
