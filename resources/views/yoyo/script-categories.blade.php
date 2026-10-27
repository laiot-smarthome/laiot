@php
    $isForm = $view === 'form';
    $heading = !$isForm
        ? 'Категории сценариев'
        : ($editing > 0 ? 'Переименование категории' : 'Новая категория');
    $subtitle = match (true) {
        !$isForm => 'Категории группируют сценарии и фильтруют список. Удаление категории не удаляет сценарии.',
        $editing > 0 => 'Измените название категории; сценарии останутся в ней.',
        default => 'Введите название — после сохранения вернётесь к списку категорий.',
    };
@endphp

<div class="md-ui-actions">
    <button type="button" class="md-btn md-btn--secondary" yoyo:get="show" yoyo:spinning.attr="disabled">
        <x-md.icon name="folder" class="md-btn__icon" />
        <span class="md-btn__label">Категории</span>
    </button>

    @if ($open)
        <dialog class="md-app-dialog md-modal" x-data="{}" x-init="$el.showModal()" @cancel.prevent="$refs.close.click()"
                @click="$event.target === $el && $refs.close.click()"
                aria-labelledby="script-categories-title">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="script-categories-title">{{ $heading }}</h2>
                        <p class="md-app-dialog__subtitle">{{ $subtitle }}</p>
                    </div>
                    <button type="button" class="btn-close" x-ref="close" yoyo:get="close" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    @if ($message !== '')
                        <x-md.alert type="success" :text="$message" />
                    @endif
                    @if ($error !== '')
                        <x-md.alert type="danger" title="Действие не выполнено" :text="$error" />
                    @endif

                    @if ($isForm)
                        <form class="md-ui-form md-ui-form-stack" yoyo:post="save">
                            <x-md.input name="title" label="Название категории" :value="$title" required autofocus
                                        placeholder="Освещение, Климат, Уведомления" hint="До 191 символа." />
                            <div class="md-ui-form-actions">
                                <x-md.button type="submit" icon="check">
                                    {{ $editing > 0 ? 'Сохранить' : 'Добавить категорию' }}
                                </x-md.button>
                            </div>
                        </form>
                    @else
                        <div class="md-app-dialog__toolbar">
                            <span class="md-app-dialog__meta">Всего категорий: {{ count($categories) }}</span>
                            <button type="button" class="md-btn md-btn--primary md-btn--sm"
                                    yoyo:get="startCreate" yoyo:spinning.attr="disabled">
                                <x-md.icon name="plus" class="md-btn__icon" />
                                <span class="md-btn__label">Добавить</span>
                            </button>
                        </div>

                        @if ($categories === [])
                            <x-md.empty-state icon="folder" title="Категорий нет"
                                              text="Нажмите «Добавить», чтобы создать первую категорию и группировать по ней сценарии."
                                              compact />
                        @else
                            <div class="md-table-wrap" aria-label="Категории сценариев">
                                <div class="md-table__scroll">
                                    <table class="md-table">
                                        <thead>
                                        <tr>
                                            <th scope="col">Категория</th>
                                            <th scope="col">Сценариев</th>
                                            <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($categories as $category)
                                            <tr>
                                                <td>
                                                    <div class="md-table__name">
                                                        <span class="md-table__icon"><x-md.icon name="folder" /></span>
                                                        <div><strong>{{ $category['title'] }}</strong></div>
                                                    </div>
                                                </td>
                                                <td>{{ $counts[$category['id']] ?? 0 }}</td>
                                                <td>
                                                    <div class="md-table__actions">
                                                        @if ($confirming === $category['id'])
                                                            <span class="md-app-dialog__confirm-text">Удалить категорию?</span>
                                                            <button type="button" class="md-btn md-btn--danger md-btn--sm"
                                                                    yoyo:post="remove({{ $category['id'] }})">Удалить</button>
                                                            <button type="button" class="md-btn md-btn--ghost md-btn--sm"
                                                                    yoyo:get="cancelDelete">Отмена</button>
                                                        @else
                                                            <x-md.icon-button icon="pencil" label="Переименовать категорию" size="sm"
                                                                              yoyo:get="startEdit({{ $category['id'] }})" />
                                                            <x-md.icon-button icon="trash" label="Удалить категорию" variant="danger" size="sm"
                                                                              yoyo:get="requestDelete({{ $category['id'] }})" />
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="modal-footer">
                    @if ($isForm)
                        <button type="button" class="md-btn md-btn--secondary" yoyo:get="cancelForm">Назад к списку</button>
                    @else
                        <button type="button" class="md-btn md-btn--secondary" yoyo:get="close">Закрыть</button>
                    @endif
                </div>
            </div>
        </dialog>
    @endif
</div>
