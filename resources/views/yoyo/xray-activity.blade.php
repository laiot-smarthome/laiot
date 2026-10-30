<section class="md-xray-console" x-data="mdXrayFeed">
    <div class="md-xray-console__bar">
        <span><x-md.icon :name="$tab === 'debug' ? 'terminal-2' : 'list-details'" /> {{ $tabs[$tab] }} <x-md.badge tone="neutral">{{ count($rows) }}</x-md.badge></span>
        @include('yoyo.xray-follow')
    </div>
    <div class="md-xray-feed" x-ref="feed" x-on:scroll.passive="rememberPosition()" tabindex="0" aria-label="Лента записей X-Ray">
        @forelse (array_reverse($rows) as $entry)
            <article class="md-xray-record md-xray-record--{{ $entry['kind'] }}">
                <span class="md-xray-record__icon"><x-md.icon :name="match($entry['kind']) {'property' => 'arrows-exchange', 'debug' => 'bug', 'script' => 'file-code', 'event' => 'bolt', default => 'settings'}" /></span>
                <div class="md-xray-record__content">
                    <div class="md-xray-record__head"><strong>{{ $entry['name'] }}</strong><div class="md-ui-actions"><x-md.badge tone="neutral">{{ $labels[$entry['kind']] ?? $entry['kind'] }}</x-md.badge><time title="{{ $entry['created_at'] }}">{{ substr($entry['created_at'], 11) }}</time></div></div>
                    @if ($entry['kind'] === 'property')
                        <div class="md-xray-diff"><code class="is-old">{{ $entry['payload']['old_value'] === '' ? '(пусто)' : mb_substr($entry['payload']['old_value'], 0, 160) }}</code><x-md.icon name="arrow-right" /><code class="is-new">{{ $entry['payload']['new_value'] === '' ? '(пусто)' : mb_substr($entry['payload']['new_value'], 0, 160) }}</code></div>
                    @elseif ($entry['kind'] === 'script')
                        <div class="md-ui-actions"><x-md.badge :tone="$entry['payload']['ok'] ? 'success' : 'danger'">{{ $entry['payload']['ok'] ? 'Выполнен' : 'Ошибка' }}</x-md.badge><span class="md-table__muted"><x-md.icon name="clock" /> {{ $entry['payload']['duration_ms'] }} мс</span></div>
                        @if ($entry['payload']['error'] !== '')<pre class="md-xray-error">{{ $entry['payload']['error'] }}</pre>@endif
                    @elseif (isset($entry['payload']['value']))
                        @if (isset($entry['payload']['value']['children']))
                            <details class="md-xray-preview" data-xray-expand>
                                <summary><code>{{ $entry['payload']['value']['type'] }}</code><span>{{ $entry['payload']['value']['count'] }} элементов</span><span class="md-xray-preview__hint">Раскрыть значение</span></summary>
                                <x-md.data-view :snapshot="$entry['payload']['value']" />
                            </details>
                        @else
                            <div class="md-xray-scalar"><x-md.badge tone="info">{{ $entry['payload']['value']['type'] }}</x-md.badge><pre>{{ var_export($entry['payload']['value']['value'] ?? null, true) }}</pre></div>
                        @endif
                    @endif
                    <div class="md-xray-record__footer"><span><x-md.icon name="code-dots" /> {{ $entry['source'] }}</span><x-md.button variant="ghost" size="sm" icon-after="arrow-up-right" yoyo:post="open({{ $entry['id'] }})">Подробнее</x-md.button></div>
                </div>
            </article>
        @empty
            <x-md.empty-state :icon="$tab === 'debug' ? 'bug' : 'activity'"
                              :title="$search !== '' ? 'Ничего не найдено' : match($tab) {'debug' => 'Отладочных записей пока нет', 'property' => 'Изменений свойств пока нет', 'script' => 'Сценарии ещё не запускались', 'event' => 'События ещё не поступали', default => 'Лента пока пуста'}"
                              :text="$search !== '' ? 'Измените запрос или очистите строку поиска.' : ($tab === 'debug' ? 'Вызовите debmes($value) или dump($value) — значение появится здесь автоматически.' : 'Новые записи появятся автоматически. Хранится активность за последние трое суток.')" />
        @endforelse
    </div>
</section>
