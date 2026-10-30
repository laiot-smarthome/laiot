<x-md.table label="Поступившие события" class="md-xray-events">
    @if ($rows !== [])
        <x-slot:head><th scope="col">Время</th><th scope="col">Событие</th><th scope="col">Данные</th></x-slot:head>
        <x-slot:body>
            @foreach ($rows as $entry)
                <tr><td><time title="{{ \LaIOT\Services\Events\EventChainService::formatTime($entry['created_at']) }}">{{ substr($entry['created_at'], 11) }}</time></td><td><strong>{{ $entry['name'] }}</strong></td><td><x-md.data-view :snapshot="$entry['payload']['value']" compact /></td></tr>
            @endforeach
        </x-slot:body>
    @else
        <x-slot:empty><x-md.empty-state icon="bolt" title="События ещё не поступали" text="Здесь появятся события ядра и модулей за последние трое суток." /></x-slot:empty>
    @endif
</x-md.table>
