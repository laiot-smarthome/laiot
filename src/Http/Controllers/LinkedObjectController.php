<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\LinkedObject\LinkedObjectService;

/**
 * Поиск объектов и контекст для `x-md.linked-object`.
 *
 * Только сессия администратора: это часть панели, не публичный API.
 */
final readonly class LinkedObjectController extends Controller
{
    public function __construct(private LinkedObjectService $linkedObjects)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $object = trim((string)$request->query->get('object', ''));
        if ($object !== '') {
            $context = $this->linkedObjects->context($object);
            if ($context === null) {
                return $this->json([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'Объект не найден.'],
                ], 404);
            }

            return $this->json(['ok' => true, 'data' => $context]);
        }

        $query = trim((string)$request->query->get('q', ''));
        $limit = (int)$request->query->get('limit', 20);

        return $this->json([
            'ok' => true,
            'data' => ['objects' => $this->linkedObjects->search($query, $limit)],
        ]);
    }
}
