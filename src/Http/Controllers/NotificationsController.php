<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers;

use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Notifications\NotificationService;
use Symfony\Component\HttpFoundation\Response;

/**
 * API центра уведомлений для колокольчика и drawer админки.
 *
 * GET  /api/v1/panel/notifications          — непрочитанные, summary и items
 * POST /api/v1/panel/notifications/dismiss  — явное прочтение одного/модуля
 */
final readonly class NotificationsController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): Response
    {
        $module = trim((string)$request->query->get('module', ''));
        $offset = max(0, (int)$request->query->get('offset', 0));

        return $this->json([
            'ok' => true,
            'data' => $this->notifications->listing($module, $offset),
        ]);
    }

    public function dismiss(Request $request): Response
    {
        $id = $request->json('id');
        $module = $request->json('module');
        $id = is_numeric($id) ? (int)$id : null;
        $module = is_string($module) ? trim($module) : null;
        $dismissed = $this->notifications->dismiss($id, $module);

        return $this->json([
            'ok' => true,
            'data' => [
                'dismissed' => $dismissed,
            ],
        ]);
    }
}
