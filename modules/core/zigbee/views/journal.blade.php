@php
    /** @var list<array<string, mixed>> $bridges */
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Интеграции · Zigbee"
        icon="history"
        title="Журнал Zigbee"
        lede="Обнаружение, ошибки доставки, команды и события мониторинга. На экземпляр хранится до 1000 последних записей."
        :meta="[
            ['icon' => 'router', 'label' => 'Экземпляры', 'value' => (string)($counts['bridges'] ?? 0)],
            ['icon' => 'device-desktop', 'label' => 'Устройств', 'value' => (string)($counts['total'] ?? 0)],
        ]">
        <x-slot:actions>
            <x-md.button href="/module/zigbee" variant="secondary" icon="arrow-left">К устройствам</x-md.button>
            <form method="post" action="/module/zigbee/journal/clear" class="md-inline-form"
                  data-md-confirm="Удалить весь журнал Zigbee?">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="ghost" icon="trash">Очистить</x-md.button>
            </form>
        </x-slot:actions>
    </x-md.page-hero>

    @yoyo('zigbee-journal', [], ['id' => 'zigbee-journal'])
</div>
