<?php

declare(strict_types=1);

namespace MajorDoMo\Http\Controllers\Core;

use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use MajorDoMo\Http\Controller;
use MajorDoMo\Http\PanelPage;
use MajorDoMo\Http\PanelRenderer;
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
 * 2026-10-27 — список и разбор записи собраны в Yoyo-компонент: фильтры,
 *              группы и выбор ошибки больше не делают переходы по страницам.
 */
final readonly class SystemErrorsController extends Controller
{
    public function __construct(
        private Factory $view,
        private ErrorRepository $errors,
        private ErrorFileWriter $files,
        private PanelRenderer $panel
    ) {
    }

    public function index(Request $request): Response
    {
        $message = trim((string)$request->query->get('message', ''));

        $content = $this->view->make('pages.system_errors.index', [
            'search' => trim((string)$request->query->get('q', '')),
            'level' => trim((string)$request->query->get('level', '')),
            'message' => $message,
            'flat' => (string)$request->query->get('flat', '') === '1' || $message !== '',
            'done' => trim((string)$request->query->get('done', '')),
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
