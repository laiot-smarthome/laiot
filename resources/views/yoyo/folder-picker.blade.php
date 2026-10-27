<div class="md-field">
    <label class="md-field__label">Каталог проекта</label>
    <div class="md-ui-actions"><input class="md-input" type="text" name="{{ $name }}" value="{{ $selected }}" aria-label="Выбранный каталог" readonly><button type="button" class="md-btn md-btn--secondary" yoyo:get="show" yoyo:spinning.attr="disabled">Выбрать папку</button><span yoyo:spinning role="status"><span class="md-spinner" aria-hidden="true"></span> Загрузка папок…</span></div>
    @if ($open)
        <dialog class="md-folder-dialog md-modal md-folder-picker" x-data="{filter: '', entries: @js($entries)}" x-init="$el.showModal()" @cancel.prevent="$refs.close.click()" aria-labelledby="folder-picker-title">
            <div class="modal-content">
                <div class="modal-header"><div><h2 class="modal-title fs-5" id="folder-picker-title">Выбор папки</h2><p class="md-folder-picker__subtitle">Навигация ограничена папкой проекта.</p></div><button type="button" class="btn-close" x-ref="close" yoyo:get="close" aria-label="Закрыть"></button></div>
                <div class="modal-body md-folder-picker__body">
                    @if (!$allowed)<p role="alert">Для выбора каталога требуется право управления настройками.</p>
                    @else
                        <div class="md-folder-picker__toolbar"><button type="button" class="md-btn md-btn--secondary md-btn--sm" yoyo:get="up" yoyo:spinning.attr="disabled" @disabled($path === '')>На уровень выше</button><span>{{ $path === '' ? 'Корень проекта' : $path }}</span></div>
                        @if ($error !== null)<p role="alert">{{ $error }}</p>@else
                            <input class="md-input" type="search" x-model="filter" placeholder="Фильтр по папкам" aria-label="Фильтр по папкам">
                            <div yoyo:spinning role="status"><span class="md-spinner" aria-hidden="true"></span> Загрузка папок…</div>
                            <div yoyo:spinning.remove><ul class="md-kit-folder__list">@foreach ($entries as $entry)<li x-show="@js(mb_strtolower($entry)).includes(filter.toLocaleLowerCase())"><button type="button" yoyo:get="enter('{{ base64_encode($entry) }}')" yoyo:spinning.attr="disabled"><x-md.icon name="folder" /> {{ $entry }}</button></li>@endforeach</ul><div x-cloak x-show="!entries.some(entry => entry.toLocaleLowerCase().includes(filter.toLocaleLowerCase()))"><x-md.empty-state icon="folder-off" title="Папки не найдены" text="Измените фильтр или выберите текущую папку." compact /></div></div>
                        @endif
                    @endif
                </div>
                <div class="modal-footer"><button type="button" class="md-btn md-btn--secondary" yoyo:get="close">Отмена</button>@if ($allowed && $error === null)<button type="button" class="md-btn md-btn--primary" yoyo:get="choose" yoyo:spinning.attr="disabled">Выбрать текущую папку</button>@endif</div>
            </div>
        </dialog>
    @endif
</div>
