@props([
    'name' => null,
    'value' => '',
    'label' => null,
    'rows' => 12,
    'hint' => null,
    'language' => 'php',
    'id' => null,
    'required' => false,
    'error' => null,
])

@php
    $id = $id ?? 'md-code-' . substr(md5(($name ?? '') . '|' . ($label ?? '')), 0, 8);
    $lineCount = max(1, substr_count((string)$value, "\n") + 1);

    // Ошибку присылает серверный валидатор: строка помогает сразу найти место,
    // а редактор по data-error-line переводит курсор на неё.
    $errorLine = 0;
    $errorText = '';
    if (is_array($error)) {
        $errorLine = max(0, (int)($error['line'] ?? 0));
        $errorText = trim((string)($error['message'] ?? ''));
    } elseif ($error !== null) {
        $errorText = trim((string)$error);
    }
    $describedBy = trim(implode(' ', array_filter([
        $errorText !== '' ? $id . '-error' : null,
        $hint !== null ? $id . '-help' : null,
    ])));
@endphp

<div class="md-field" x-data="{code: @js((string)$value)}">
    @if ($label !== null)
        <label class="md-field__label" for="{{ $id }}">
            {{ $label }}
            @if ($required)<span class="md-field__req" aria-hidden="true">*</span>@endif
        </label>
    @endif

    @if ($errorText !== '')
        <div class="md-code-editor__error" id="{{ $id }}-error" role="alert">
            <x-md.icon name="alert-circle" />
            <span>
                <strong>{{ $errorLine > 0 ? 'Строка ' . $errorLine . ':' : 'Синтаксическая ошибка:' }}</strong>
                {{ $errorText }}
            </span>
        </div>
    @endif

    <div class="md-code-editor">
        <div class="md-code-editor__toolbar" data-editor-toolbar hidden>
            <span><x-md.icon name="code" /> {{ strtoupper($language) }}</span>
            <div class="md-ui-actions">
                <button type="button" class="md-icon-btn md-icon-btn--sm" data-editor-action="undo" aria-label="Отменить" title="Отменить · Ctrl+Z"><x-md.icon name="arrow-back-up" /></button>
                <button type="button" class="md-icon-btn md-icon-btn--sm" data-editor-action="redo" aria-label="Повторить" title="Повторить · Ctrl+Shift+Z"><x-md.icon name="arrow-forward-up" /></button>
                <button type="button" class="md-icon-btn md-icon-btn--sm" data-editor-action="search" aria-label="Поиск в коде" title="Поиск · Ctrl+F"><x-md.icon name="search" /></button>
                <button type="button" class="md-icon-btn md-icon-btn--sm" data-editor-action="goto" aria-label="Перейти к строке" title="Перейти к строке · Ctrl+G"><x-md.icon name="list-numbers" /></button>
                <button type="button" class="md-icon-btn md-icon-btn--sm" data-editor-action="comment" aria-label="Комментировать строки" title="Комментировать · Ctrl+/"><x-md.icon name="code" /></button>
                <button type="button" class="md-icon-btn md-icon-btn--sm" data-editor-action="complete" aria-label="Подсказки кода" title="Подсказки · Ctrl+Space"><x-md.icon name="bulb" /></button>
                @if ($language === 'php')<button type="button" class="md-icon-btn md-icon-btn--sm" data-editor-action="check" aria-label="Проверить синтаксис" title="Предварительная проверка синтаксиса PHP"><x-md.icon name="circle-check" /></button>@endif
                <details class="md-code-editor__help"><summary class="md-icon-btn md-icon-btn--sm" aria-label="Горячие клавиши" title="Горячие клавиши"><x-md.icon name="keyboard" /></summary><div class="md-code-editor__shortcuts"><strong>Горячие клавиши</strong><p><kbd>Ctrl</kbd> + <kbd>F</kbd> — поиск и замена</p><p><kbd>Ctrl</kbd> + <kbd>G</kbd> — перейти к строке</p><p><kbd>Ctrl</kbd> + <kbd>/</kbd> — закомментировать</p><p><kbd>Ctrl</kbd> + <kbd>Space</kbd> — подсказки</p><p><kbd>Tab</kbd> / <kbd>Shift+Tab</kbd> — отступ</p><p><kbd>Ctrl</kbd> + <kbd>Z</kbd> / <kbd>Shift+Ctrl+Z</kbd> — отмена / повтор</p><p>На macOS вместо Ctrl используйте ⌘.</p></div></details>
            </div>
        </div>
        <pre class="md-code-editor__gutter" aria-hidden="true"
             x-text="Array.from({length: Math.max(1, code.split('\n').length)}, (_, i) => i + 1).join('\n')">{{ implode("\n", range(1, $lineCount)) }}</pre>
        <textarea class="md-code-editor__input"
                  id="{{ $id }}"
                  @if ($name !== null) name="{{ $name }}" @endif
                  rows="{{ $rows }}"
                  spellcheck="false"
                  autocapitalize="off"
                  autocomplete="off"
                  data-code-editor
                  data-language="{{ $language }}"
                  @if ($errorLine > 0) data-error-line="{{ $errorLine }}" @endif
                  @if ($errorText !== '') aria-invalid="true" @endif
                  @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                  x-model="code"
                  @if ($required) required @endif>{{ $value }}</textarea>
    </div>

    @if ($hint !== null)
        <div class="md-field__hint" id="{{ $id }}-help"><span>{{ $hint }}</span></div>
    @endif
    @if ($errorText !== '')
        <div class="md-field__error">Проверьте парность скобок и кавычек, завершайте операторы точкой с запятой.</div>
    @endif
    @if ($language === 'php')
        <div class="md-field__hint">Синтаксис проверяется на сервере перед сохранением; запуск код не выполняет.</div>
    @endif
</div>
