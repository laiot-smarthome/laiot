<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Ошибки"
        icon="file-text"
        :title="'Ошибка #' . $error['id']"
        :lede="$error['message']"
        :meta="[
            ['icon' => 'alert-triangle', 'label' => 'Уровень', 'value' => $error['level']],
            ['icon' => 'calendar', 'label' => 'Когда', 'value' => $error['created_at']],
        ]">
        <x-slot:actions>
            <x-md.button href="/errors?flat=1" variant="secondary" icon="arrow-left">К списку ошибок</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($file['truncated'])
        <x-md.alert type="warning" title="Файл показан не полностью"
                    text="Показаны первые 512 КБ. Полный файл лежит на сервере: var/log/errors/{{ $file['name'] }}." />
    @endif

    <x-md.card title="Файл ошибки" :subtitle="$file['name']">
        <dl class="md-dash-kv">
            <div><dt>Размер</dt><dd>{{ number_format($file['size'] / 1024, 1, ',', ' ') }} КБ</dd></div>
            <div><dt>Изменён</dt><dd>{{ $file['modified'] }}</dd></div>
            @if ($error['request_id'] !== '')
                <div><dt>Request ID</dt><dd><code class="md-table__code">{{ $error['request_id'] }}</code></dd></div>
            @endif
        </dl>

        <div class="md-prose">
            <pre>{{ $file['content'] }}</pre>
        </div>
    </x-md.card>
</div>
