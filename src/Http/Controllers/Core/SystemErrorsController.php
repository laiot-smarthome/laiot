<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
use MajorDoMo\Http\Session\SessionStore;
use MajorDoMo\Logging\ErrorFileWriter;
use MajorDoMo\Logging\ErrorRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ошибки ядра: группы одинаковых сообщений и плоский журнал.
 *
 * alpha-system_errors группировал повторы и позволял чистить группу целиком.
 * Схема MyDoMo хранит только записи, поэтому группа считается на чтении по
 * паре level + message, а удаление группы — одно обновление с тем же условием.
 *
 * 2026-10-26 — добавлены группировка, поиск по сообщению и удаление группы.
 * 2026-10-27 — просмотр отдельного файла ошибки.
 */
final readonly class SystemErrorsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ErrorRepository $errors,
        private ErrorFileWriter $files,
        private SessionStore $session,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $level = trim((string)$request->query->get('level', ''));
        $search = trim((string)$request->query->get('q', ''));
        $message = trim((string)$request->query->get('message', ''));
        $flat = (string)$request->query->get('flat', '') === '1' || $message !== '';

        $rows = $this->errors->latest(500, $level);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => str_contains(mb_strtolower($row['message']), $needle)
                    || str_contains(mb_strtolower($row['request_id']), $needle)
            ));
        }
        if ($message !== '') {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['message'] === $message));
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = $row['level'] . '|' . $row['message'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'level' => $row['level'],
                    'message' => $row['message'],
                    'count' => 0,
                    'first_at' => $row['created_at'],
                    'last_at' => $row['created_at'],
                    'request_id' => $row['request_id'],
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['last_at'] = max($groups[$key]['last_at'], $row['created_at']);
            $groups[$key]['first_at'] = min($groups[$key]['first_at'], $row['created_at']);
            if ($row['request_id'] !== '') {
                $groups[$key]['request_id'] = $row['request_id'];
            }
        }
        $groups = array_values($groups);
        usort($groups, static fn (array $left, array $right): int => strcmp($right['last_at'], $left['last_at']));

        $content = $this->view->make('pages.system_errors.index', [
            'errors' => $rows,
            'groups' => $groups,
            'level' => $level,
            'search' => $search,
            'message' => $message,
            'flat' => $flat,
            'done' => trim((string)$request->query->get('done', '')),
            'csrf' => $this->session->csrfToken(),
        ])->render();

        return $this->panel->render(new PanelPage('Ошибки', $content, 'system_errors'));
    }

    public function file(string $id): Response
    {
        $error = $this->errors->find((int)$id);
        if ($error === null) {
            return $this->text('Ошибка не найдена.', 404);
        }
        $file = $this->files->read($error['log_file']);
        if ($file === null) {
            return $this->text('Файл ошибки не найден.', 404);
        }

        $content = $this->view->make('pages.system_errors.file', [
            'error' => $error,
            'file' => $file,
        ])->render();

        return $this->panel->render(new PanelPage('Ошибка #' . $error['id'], $content, 'system_errors'));
    }

    public function delete(string $id): Response
    {
        $this->errors->delete((int)$id);

        return new RedirectResponse('/errors');
    }

    public function deleteGroup(Request $request): Response
    {
        $level = (string)$request->request->get('level', '');
        $message = (string)$request->request->get('message', '');
        $deleted = $this->errors->deleteGroup($level, $message);

        return new RedirectResponse('/errors?done=' . rawurlencode('Удалено записей: ' . $deleted . '.'));
    }
}
