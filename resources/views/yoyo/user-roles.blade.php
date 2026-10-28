<div class="md-ui-actions">
    @if ($allowed)
        <x-md.button variant="secondary" icon="shield" yoyo:get="show" yoyo:spinning.attr="disabled">Роли и права</x-md.button>
    @endif
    @if ($open)
        <dialog class="md-app-dialog md-modal" x-data="{}" x-init="$el.showModal()"
                @cancel.prevent="$refs.close.click()" @click="$event.target === $el && $refs.close.click()"
                aria-labelledby="user-roles-title">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="user-roles-title">{{ $editingForm ? ($editing > 0 ? 'Изменить роль' : 'Новая роль') : 'Роли и права' }}</h2>
                        <p class="md-app-dialog__subtitle">Роль объединяет разрешения. Пользователь получает права всех назначенных ролей.</p>
                    </div>
                    <button type="button" class="btn-close" x-ref="close" yoyo:get="close" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    @if (!$allowed)
                        <x-md.alert type="danger" text="Требуется право управления пользователями." />
                    @else
                        @if ($editingForm)
                            <form class="md-ui-form md-ui-form-stack" yoyo:post="save">
                                <x-md.input name="role_title" label="Название роли" :value="$title" required maxlength="191" autofocus />
                                <x-md.input name="role_name" label="Код роли" :value="$name" required :readonly="$system"
                                            pattern="[a-z][a-z0-9_]{1,63}" minlength="2" maxlength="64"
                                            hint="2–64 символа: строчные латинские буквы, цифры и _. Системный код менять нельзя." />
                                <fieldset class="md-ui-form-stack">
                                    <legend class="fs-6">Разрешения</legend>
                                    <p class="md-table__muted">Права задаются ядром и установленными модулями. Пустая роль ничего не разрешает.</p>
                                    @forelse ($permissions as $permission)
                                        <x-md.checkbox name="permission_ids[]" :value="(string)$permission['id']"
                                                       :label="$permission['title']" :description="$permission['name']"
                                                       :checked="in_array($permission['id'], $selected, true)" />
                                    @empty
                                        <x-md.empty-state title="Разрешений нет" text="Примените миграции ядра." compact />
                                    @endforelse
                                </fieldset>
                                <div class="md-ui-form-actions">
                                    <x-md.button type="submit" icon="check" yoyo:spinning.attr="disabled">Сохранить роль</x-md.button>
                                </div>
                            </form>
                        @else
                            <div class="md-app-dialog__toolbar">
                                <span class="md-app-dialog__meta">Ролей: {{ count($roles) }} · Разрешений: {{ count($permissions) }}</span>
                                <x-md.button size="sm" icon="plus" yoyo:get="create" yoyo:spinning.attr="disabled">Добавить роль</x-md.button>
                            </div>
                            @forelse ($roles as $role)
                                <x-md.card :title="$role['title']" :subtitle="$role['name'] . ' · Пользователей: ' . ($counts[$role['id']] ?? 0)">
                                    <div class="md-ui-form-stack">
                                        <div class="md-ui-actions">
                                            @foreach ($permissions as $permission)
                                                @if (in_array($permission['id'], $grants[$role['id']] ?? [], true))
                                                    <x-md.badge tone="outline">{{ $permission['title'] }}</x-md.badge>
                                                @endif
                                            @endforeach
                                            @if (($grants[$role['id']] ?? []) === [])<span class="md-table__muted">Нет разрешений</span>@endif
                                        </div>
                                        <div class="md-ui-actions">
                                            <x-md.button size="sm" variant="secondary" icon="pencil" yoyo:get="edit({{ $role['id'] }})">Изменить</x-md.button>
                                            @if ($role['is_system'])
                                                <x-md.badge tone="neutral">Системная роль</x-md.badge>
                                            @elseif ($confirming === $role['id'])
                                                <span>Удалить роль?</span>
                                                <x-md.button size="sm" variant="danger" yoyo:post="remove({{ $role['id'] }})" yoyo:spinning.attr="disabled">Удалить</x-md.button>
                                                <x-md.button size="sm" variant="ghost" yoyo:get="confirm(0)">Отмена</x-md.button>
                                            @else
                                                <x-md.icon-button icon="trash" label="Удалить роль" variant="danger" yoyo:get="confirm({{ $role['id'] }})" />
                                            @endif
                                        </div>
                                    </div>
                                </x-md.card>
                            @empty
                                <x-md.empty-state icon="shield" title="Ролей нет" text="Добавьте роль и выберите разрешения." compact />
                            @endforelse
                        @endif
                    @endif
                </div>
                <div class="modal-footer">
                    @if ($editingForm)<x-md.button variant="secondary" yoyo:get="back">К списку ролей</x-md.button>
                    @else<x-md.button variant="secondary" yoyo:get="close">Закрыть</x-md.button>@endif
                </div>
            </div>
        </dialog>
    @endif
</div>
