<x-md.table label="Изменения свойств" class="md-xray-properties">
    <x-slot:toolbar><span class="md-table__meta">Последние 50 изменений · новые вверху</span><span class="md-table__muted">Для чтения потока нажмите «Пауза»</span></x-slot:toolbar>
    @if ($rows !== [])
        <x-slot:head><th scope="col">Время</th><th scope="col">Свойство</th><th scope="col">Было → стало</th><th scope="col">Источник</th></x-slot:head>
        <x-slot:body>
            @foreach ($rows as $entry)
                <tr>
                    <td><time title="{{ $entry['created_at'] }}">{{ substr($entry['created_at'], 11) }}</time></td>
                    <td><strong title="{{ $entry['name'] }}">{{ $entry['name'] }}</strong></td>
                    <td><x-md.value-change :old="$entry['payload']['old_value']" :new="$entry['payload']['new_value']" /></td>
                    <td><span title="{{ $entry['source'] }}">{{ $entry['source'] }}</span></td>
                </tr>
            @endforeach
        </x-slot:body>
    @else
        <x-slot:empty><x-md.empty-state icon="arrows-exchange" title="Изменения не найдены" text="Здесь появятся изменения свойств, включая свойства с выключенной историей." /></x-slot:empty>
    @endif
</x-md.table>
