<?php

declare(strict_types=1);

namespace MajorDoMo\Yoyo;

use Clickfwd\Yoyo\Component;
use Clickfwd\Yoyo\Yoyo;
use InvalidArgumentException;
use MajorDoMo\Audit\ActionLogger;
use MajorDoMo\Auth\Authorization;
use MajorDoMo\Auth\RoleRepository;

/**
 * Роли и разрешения внутри раздела пользователей.
 *
 * 2026-10-27 — список, форма и подтверждение удаления в одном диалоге;
 * каждый запрос проверяет право, изменения доступны только через POST.
 */
final class UserRoles extends Component
{
    public bool $open = false;
    public bool $editingForm = false;
    public int $editing = 0;
    public int $confirming = 0;

    /** @var list<string> */
    protected $props = ['open', 'editingForm', 'editing', 'confirming'];

    private ?RoleRepository $roles = null;
    private ?Authorization $authorization = null;
    private ?ActionLogger $audit = null;
    private string $name = '';
    private string $title = '';
    /** @var list<int> */
    private array $selected = [];
    private string $error = '';
    private string $message = '';

    public function mount(RoleRepository $roles, Authorization $authorization, ActionLogger $audit): void
    {
        $this->roles = $roles;
        $this->authorization = $authorization;
        $this->audit = $audit;
    }

    public function show(): void
    {
        $this->open = true;
        $this->editingForm = false;
        $this->confirming = 0;
    }

    public function close(): void
    {
        $this->open = false;
        $this->editingForm = false;
        $this->confirming = 0;
    }

    public function create(): void
    {
        $this->open = true;
        $this->editingForm = true;
        $this->editing = 0;
        $this->confirming = 0;
    }

    public function edit(int $id): void
    {
        if (!$this->allowed()) {
            return;
        }
        $role = $this->roles?->find($id);
        if ($role === null) {
            $this->error = 'Роль не найдена.';
            return;
        }
        $this->open = true;
        $this->editingForm = true;
        $this->editing = $id;
        $this->confirming = 0;
        $this->name = $role['name'];
        $this->title = $role['title'];
        $this->selected = $this->roles->permissionsByRole()[$id] ?? [];
    }

    public function back(): void
    {
        $this->editingForm = false;
        $this->editing = 0;
        $this->confirming = 0;
    }

    public function confirm(int $id): void
    {
        $this->confirming = $id;
    }

    public function save(): void
    {
        if (!$this->writable() || $this->roles === null) {
            return;
        }
        $this->name = trim((string)$this->getQueryParam('role_name', ''));
        $this->title = trim((string)$this->getQueryParam('role_title', ''));
        $ids = $this->getQueryParam('permission_ids', []);
        try {
            if (!is_array($ids)) {
                throw new InvalidArgumentException('Некорректный список разрешений.');
            }
            $id = $this->roles->save($this->editing, $this->name, $this->title, $ids);
            $this->audit?->log('role.save', 'Сохранена роль #' . $id . ': ' . $this->name, (string)($this->authorization?->user()['username'] ?? ''));
        } catch (InvalidArgumentException $error) {
            $this->error = $error->getMessage();
            $this->selected = is_array($ids) ? array_values(array_map('intval', array_filter($ids, 'is_scalar'))) : [];
            $this->editingForm = true;
            return;
        }
        $this->editingForm = false;
        $this->editing = 0;
        $this->message = 'Роль сохранена. Назначения действуют со следующего запроса.';
    }

    public function remove(int $id): void
    {
        if (!$this->writable() || $this->roles === null) {
            return;
        }
        try {
            $this->roles->delete($id);
            $this->audit?->log('role.delete', 'Удалена роль #' . $id, (string)($this->authorization?->user()['username'] ?? ''));
            $this->message = 'Роль удалена.';
            $this->confirming = 0;
        } catch (InvalidArgumentException $error) {
            $this->error = $error->getMessage();
        }
    }

    public function render(): mixed
    {
        $allowed = $this->allowed();
        // Состояние формы восстанавливаем по id из БД, а введённые поля
        // остаются приватными: hx-vals не должен перекрывать поля формы.
        if ($allowed && $this->editingForm && $this->editing > 0 && $this->name === '' && $this->error === '') {
            $role = $this->roles?->find($this->editing);
            $this->name = $role['name'] ?? '';
            $this->title = $role['title'] ?? '';
            $this->selected = $this->roles?->permissionsByRole()[$this->editing] ?? [];
        }

        return $this->view('user-roles', [
            'allowed' => $allowed, 'open' => $this->open, 'editingForm' => $this->editingForm,
            'editing' => $this->editing, 'confirming' => $this->confirming,
            'roles' => $allowed ? $this->roles?->allRoles() ?? [] : [],
            'permissions' => $allowed ? $this->roles?->allPermissions() ?? [] : [],
            'grants' => $allowed ? $this->roles?->permissionsByRole() ?? [] : [],
            'counts' => $allowed ? $this->roles?->userCounts() ?? [] : [],
            'name' => $this->name, 'title' => $this->title, 'selected' => $this->selected,
            'error' => $this->error, 'message' => $this->message,
            'system' => $allowed && ($this->roles?->find($this->editing)['is_system'] ?? false),
        ]);
    }

    private function allowed(): bool
    {
        return $this->authorization?->can('users.manage') === true;
    }

    private function writable(): bool
    {
        if (!$this->allowed()) {
            $this->error = 'Требуется право управления пользователями.';
            return false;
        }
        if (Yoyo::request()->method() !== 'POST') {
            $this->error = 'Изменение разрешено только POST-запросом.';
            return false;
        }

        return true;
    }
}
