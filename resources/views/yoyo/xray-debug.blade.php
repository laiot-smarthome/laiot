<div class="md-xray-debug-list" x-data="mdXrayFeed">
    <div class="md-xray-console__bar"><span><x-md.icon name="terminal-2" /> Отладочный вывод <x-md.badge tone="neutral">{{ count($rows) }}</x-md.badge></span>@include('yoyo.xray-follow')</div>
    <div class="md-xray-feed md-xray-debug-feed" x-ref="feed" x-on:scroll.passive="rememberPosition()" tabindex="0" aria-label="Отладочные записи X-Ray">
        @forelse (array_reverse($rows) as $entry)
            <article class="md-xray-debug-entry">
                <div class="md-xray-debug-entry__head"><div><x-md.icon name="bug" /><strong>{{ $entry['name'] }}</strong><span>#{{ $entry['id'] }}</span></div><time title="{{ $entry['created_at'] }}">{{ substr($entry['created_at'], 11) }}</time></div>
                <div class="md-xray-debug-code">
                    <x-md.data-view :snapshot="$entry['payload']['value']" />
                </div>
                <div class="md-xray-debug-entry__footer"><span><x-md.icon name="code-dots" /> {{ $entry['source'] }}</span><x-md.button variant="ghost" size="sm" icon-after="arrow-up-right" yoyo:post="open({{ $entry['id'] }})">Подробнее</x-md.button></div>
            </article>
        @empty
            <x-md.empty-state icon="bug" :title="$search === '' ? 'Отладочный вывод пока пуст' : 'Записи не найдены'" text="Вызовите debmes($value) или dump($value) в коде с подключённым ядром." />
        @endforelse
    </div>
</div>
