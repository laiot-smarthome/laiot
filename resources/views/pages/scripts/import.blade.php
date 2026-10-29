<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Автоматизация"
        icon="upload"
        title="Импорт сценариев"
        lede="Вставьте JSON, полученный экспортом LaIOT. Сценарии с существующим названием пропускаются — существующие не перезаписываются."
        :meta="[['icon' => 'file-code', 'label' => 'Формат', 'value' => 'JSON-массив']]">
        <x-slot:actions>
            <x-md.button href="/scripts" variant="secondary" icon="arrow-left">К скриптам</x-md.button>
            <x-md.button href="/scripts/export" variant="ghost" icon="download">Экспорт</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if (($error ?? '') !== '')
        <x-md.notify type="danger" title="Импорт не выполнен" :text="$error" />
    @endif

    <form method="post" action="/scripts/import" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Данные JSON">
            <x-md.textarea name="payload" label="JSON сценариев" rows="16" required
                           hint="Массив объектов: title, description, code, category_id, event_name." />
            <x-md.hint tone="info" title="Формат">
                <code class="md-table__code">[{"title": "Утренний свет", "code": "cm('Спальня.turnOn');", "description": "...", "event_name": "system.minute"}]</code>
                <span class="md-ui-muted">Лишние поля игнорируются; сценарии с существующим названием пропускаются, а код с синтаксической ошибкой не сохраняется.</span>
            </x-md.hint>
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="upload">Импортировать</x-md.button>
            <x-md.button href="/scripts" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
