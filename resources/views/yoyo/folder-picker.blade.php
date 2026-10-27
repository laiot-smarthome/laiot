<div class="md-field">
    <label class="md-field__label">Каталог проекта</label>
    <div class="md-ui-actions"><input class="md-input" type="text" name="{{ $name }}" value="{{ $selected }}" aria-label="Выбранный каталог" readonly><button type="button" class="md-btn md-btn--secondary" yoyo:get="show">Выбрать папку</button></div>
    @if ($open)
        <div class="md-kit-folder" role="region" aria-label="Выбор папки">
            @if (!$allowed)<p role="alert">Для выбора каталога требуется право управления настройками.</p>
            @elseif ($error !== null)<p role="alert">{{ $error }}</p><button type="button" class="md-btn md-btn--secondary md-btn--sm" yoyo:get="up">На уровень выше</button>
            @else
                <div class="md-ui-actions"><button type="button" class="md-btn md-btn--secondary md-btn--sm" yoyo:get="up" @disabled($path === '')>На уровень выше</button><span>{{ $path === '' ? 'Корень проекта' : $path }}</span></div>
                <ul class="md-kit-folder__list">@forelse ($entries as $entry)<li><button type="button" yoyo:get="enter('{{ base64_encode($entry) }}')"><x-md.icon name="folder" /> {{ $entry }}</button></li>@empty<li>Нет вложенных папок.</li>@endforelse</ul>
                <div class="md-ui-actions"><button type="button" class="md-btn md-btn--primary" yoyo:get="choose">Выбрать текущую папку</button><button type="button" class="md-btn md-btn--secondary" yoyo:get="close">Отмена</button></div>
            @endif
        </div>
    @endif
</div>
