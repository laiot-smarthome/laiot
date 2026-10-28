<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use InvalidArgumentException;
use MajorDoMo\Scripts\ScriptRepository;

/**
 * Категории сценариев: модальное управление без отдельной страницы.
 *
 * По умолчанию модалка показывает список. «Добавить» и карандаш в строке
 * переключают её в режим формы, а сохранение возвращает список — контент
 * перерисовывает Yoyo, страница не перезагружается. Изменяющие действия
 * отправляются POST с CSRF, поэтому бизнес-операция не выполняется по GET.
 *
 * 2026-10-28 — Notify отправляется событием браузера из ответа Yoyo.
 * 2026-10-27 — список и форма стали двумя состояниями модалки.
 * 2026-10-27 — категории вынесены с главной страницы сценариев в модалку.
 */
final class ScriptCategories extends Component
{
    public const VIEW_LIST = 'list';

    public const VIEW_FORM = 'form';

    public bool $open = false;

    public string $view = self::VIEW_LIST;

    public int $editing = 0;

    public int $confirming = 0;

    private string $error = '';

    private string $message = '';

    /**
     * Название для формы.
     *
     * Свойство намеренно приватное: публичное значение Yoyo сериализует в
     * `hx-vals`, и оно перекрыло бы введённый текст формы при отправке.
     * Введённое значение читается из запроса в `save()`.
     */
    private string $title = '';

    /** @var list<string> */
    protected $props = ['open', 'view', 'editing', 'confirming'];

    private ?ScriptRepository $scripts = null;

    public function mount(ScriptRepository $scripts): void
    {
        $this->scripts = $scripts;
    }

    public function show(): void
    {
        $this->open = true;
        $this->view = self::VIEW_LIST;
        $this->error = '';
        $this->message = '';
    }

    public function close(): void
    {
        $this->open = false;
        $this->view = self::VIEW_LIST;
        $this->editing = 0;
        $this->confirming = 0;
        $this->title = '';
        $this->error = '';
        $this->message = '';
    }

    /**
     * Открывает пустую форму создания поверх списка.
     */
    public function startCreate(): void
    {
        $this->view = self::VIEW_FORM;
        $this->editing = 0;
        $this->confirming = 0;
        $this->title = '';
        $this->error = '';
        $this->message = '';
    }

    /**
     * Открывает форму с текущим названием категории.
     */
    public function startEdit(int $id): void
    {
        $this->error = '';
        $this->message = '';
        $this->confirming = 0;
        $category = $this->category($id);
        if ($category === null) {
            $this->error = 'Категория не найдена.';

            return;
        }
        $this->view = self::VIEW_FORM;
        $this->editing = (int)$category['id'];
        $this->title = (string)$category['title'];
    }

    /**
     * Возвращает список, ничего не сохраняя.
     */
    public function cancelForm(): void
    {
        $this->view = self::VIEW_LIST;
        $this->editing = 0;
        $this->title = '';
        $this->error = '';
        $this->message = '';
    }

    /**
     * Сохраняет создание или переименование и возвращает список.
     */
    public function save(): void
    {
        $this->error = '';
        $this->message = '';
        if ($this->scripts === null) {
            return;
        }

        $title = $this->inputTitle();
        try {
            if ($this->editing > 0) {
                $this->scripts->renameCategory($this->editing, $title);
                $this->message = 'Категория переименована.';
            } else {
                $this->scripts->createCategory($title);
                $this->message = 'Категория создана.';
            }
        } catch (InvalidArgumentException $error) {
            // Остаёмся в форме, чтобы введённое название не потерялось.
            $this->error = $error->getMessage();
            $this->title = $title;

            return;
        }

        $this->view = self::VIEW_LIST;
        $this->editing = 0;
        $this->title = '';
    }

    /**
     * Первый шаг удаления: показываем подтверждение в строке, без window.confirm.
     */
    public function requestDelete(int $id): void
    {
        $this->confirming = $id;
        $this->view = self::VIEW_LIST;
        $this->editing = 0;
        $this->error = '';
        $this->message = '';
    }

    public function cancelDelete(): void
    {
        $this->confirming = 0;
    }

    public function remove(int $id): void
    {
        $this->error = '';
        if ($this->scripts === null || $id <= 0) {
            return;
        }

        $this->scripts->deleteCategory($id);
        $this->view = self::VIEW_LIST;
        $this->confirming = 0;
        $this->editing = 0;
        $this->title = '';
        $this->message = 'Категория удалена, сценарии остались без категории.';
    }

    public function render(): mixed
    {
        if ($this->message !== '') {
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'success', 'text' => $this->message]);
        }
        if ($this->error !== '') {
            $this->dispatchBrowserEvent('md:notify', ['tone' => 'danger', 'text' => $this->error]);
        }

        return $this->view('script-categories', [
            'categories' => $this->scripts?->categories() ?? [],
            'counts' => $this->scripts?->categoryCounts() ?? [],
            'open' => $this->open,
            'view' => $this->view,
            'editing' => $this->editing,
            'confirming' => $this->confirming,
            'title' => $this->title,
            'error' => $this->error,
            'message' => $this->message,
        ]);
    }

    /**
     * @return array{id:int,title:string}|null
     */
    private function category(int $id): ?array
    {
        foreach ($this->scripts?->categories() ?? [] as $category) {
            if ($category['id'] === $id) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Введённое название: читается из запроса, а не из публичного свойства.
     */
    private function inputTitle(): string
    {
        return trim((string)$this->getQueryParam('title', ''));
    }
}
