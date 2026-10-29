<div>
    <x-md.table label="Цепочки событий">
        <x-slot:toolbar>
            <div class="md-ui-search">
                <x-md.input type="search" name="search" id="event-chain-search" :value="$search"
                            placeholder="ID цепочки, источник, объект или действие" aria-label="Поиск цепочек"
                            autocomplete="off" yoyo:get="filter" yoyo:on="input delay:300ms changed" />
                <select class="md-table__filter" name="type" aria-label="Тип шага в цепочке" yoyo:get="filter">
                    <option value="">Все типы шагов</option>
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="md-table__filter" name="status" aria-label="Статус шага в цепочке" yoyo:get="filter">
                    <option value="">Все статусы шагов</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-md.button variant="secondary" icon="filter-off" yoyo:get="reset">Сбросить</x-md.button>
            </div>
            <div class="md-table__meta" role="status" aria-live="polite">Цепочек на странице: {{ count($chains) }}</div>
        </x-slot:toolbar>
        @if ($chains !== [])
            <x-slot:head>
                <th scope="col">Цепочка</th>
                <th scope="col">Запущена</th>
                <th scope="col">Источник</th>
                <th scope="col">Первое действие</th>
                <th scope="col">Шагов</th>
                <th scope="col">Состояние</th>
                <th scope="col"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>
            <x-slot:body>
                @foreach ($chains as $chain)
                    <tr>
                        <td><strong>#{{ $chain['id'] }}</strong>
                            @if ($chain['partial'])<div class="md-table__muted">Часть цепочки</div>@endif
                        </td>
                        <td>{{ \LaIOT\Services\Events\EventChainService::formatTime($chain['root']['created_at']) }}</td>
                        <td><span title="{{ $chain['root']['source'] }}">{{ \LaIOT\Services\Events\EventChainService::sourceLabel($chain['root']['source']) }}</span></td>
                        <td>
                            <strong>{{ $chain['root']['object_title'] }}.{{ $chain['root']['type'] === 'property.changed' ? $chain['root']['property_title'] : $chain['root']['handler'] }}</strong>
                            @if ($chain['root']['type'] === 'property.changed')
                                <div class="md-table__muted">{{ $chain['root']['old_value'] === '' ? '∅' : $chain['root']['old_value'] }} → {{ $chain['root']['new_value'] === '' ? '∅' : $chain['root']['new_value'] }}</div>
                            @endif
                        </td>
                        <td>{{ $chain['count'] }}</td>
                        <td><x-md.badge :tone="$chainTones[$chain['status']]">{{ $chainLabels[$chain['status']] }}</x-md.badge></td>
                        <td>
                            <a class="md-btn md-btn--secondary md-btn--sm"
                               href="/events?{{ http_build_query($context + ['chain' => $chain['id']]) }}"
                               yoyo:get="open({{ $chain['id'] }})" aria-label="Подробнее о цепочке #{{ $chain['id'] }}">
                                <span class="md-btn__label">Подробнее</span><x-md.icon name="arrow-right" />
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @else
            <x-slot:empty>
                <x-md.empty-state icon="route" title="Цепочки не найдены"
                                  text="Измените запрос или сбросьте фильтры. Новые цепочки появятся после обработки изменений свойств." />
            </x-slot:empty>
        @endif
        <x-slot:footer>
            <span>Поиск и фильтры находят цепочку по любому её шагу.</span>
            <div class="md-ui-actions">
                <x-md.button variant="ghost" size="sm" icon="chevron-left" yoyo:get="previousPage" :disabled="$page <= 1">Назад</x-md.button>
                <span>Страница {{ $page }}</span>
                <x-md.button variant="ghost" size="sm" icon-after="chevron-right" yoyo:get="nextPage" :disabled="!$more">Далее</x-md.button>
            </div>
        </x-slot:footer>
    </x-md.table>
</div>
