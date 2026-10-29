@php
    $levelTones = ['error' => 'danger', 'critical' => 'danger', 'warning' => 'warning', 'notice' => 'info', 'info' => 'neutral'];
    $formatTime = static function (?string $value): string {
        if ($value === null || $value === '' || strlen($value) < 19) {
            return $value ?? '—';
        }

        return substr($value, 8, 2) . '.' . substr($value, 5, 2) . '.' . substr($value, 0, 4)
            . ' ' . substr($value, 11, 8);
    };
@endphp

<div class="md-errors-list-screen" yoyo:include=".md-errors-filters">
    <h2 class="visually-hidden">{{ $flat ? 'Записи ошибок' : 'Группы ошибок' }}</h2>
    <x-md.table :label="$flat ? 'Записи ошибок' : 'Группы ошибок'" class="md-errors-table">
        <x-slot:toolbar>
            <div class="md-ui-search md-errors-filters" role="search" aria-label="Фильтры журнала ошибок">
                <input class="md-input" type="search" name="search" value="{{ $search }}"
                       placeholder="Сообщение или Request ID" aria-label="Поиск ошибок"
                       autocomplete="off" yoyo yoyo:on="input delay:300ms changed">
                <select class="md-table__filter" name="level" aria-label="Уровень ошибки" yoyo>
                    <option value="">Все уровни</option>
                    @foreach (['error', 'critical', 'warning', 'notice', 'info'] as $levelName)
                        <option value="{{ $levelName }}" @selected($level === $levelName)>{{ $levelName }}</option>
                    @endforeach
                </select>
                <button type="button" class="md-btn md-btn--secondary" yoyo:get="toggleFlat" aria-pressed="{{ $flat ? 'true' : 'false' }}">
                    <i class="ti ti-{{ $flat ? 'layers' : 'list' }}" aria-hidden="true"></i>
                    <span class="md-btn__label">{{ $flat ? 'Группы' : 'Все записи' }}</span>
                </button>
                <button type="button" class="md-btn md-btn--ghost" yoyo:get="reset" aria-label="Сбросить фильтры">
                    <i class="ti ti-filter-off" aria-hidden="true"></i><span class="md-btn__label">Сбросить</span>
                </button>
            </div>
            <div class="md-table__meta">
                @if ($message !== '')
                    <button type="button" class="md-btn md-btn--ghost md-btn--sm" yoyo:get="allGroups">
                        <i class="ti ti-arrow-left" aria-hidden="true"></i> Все группы
                    </button>
                    <span class="md-errors-muted" title="{{ $message }}">одно сообщение</span>
                    <span>·</span>
                @endif
                <span>Групп: {{ count($groups) }}</span>
                <span>·</span>
                <span>записей: {{ count($rows) }}</span>
            </div>
        </x-slot:toolbar>

        @if ($rows === [])
            <x-slot:empty>
                <x-md.empty-state icon="circle-check" :title="$search !== '' || $level !== '' ? 'Ошибок не найдено' : 'Ошибок нет'"
                                  :text="$search !== '' || $level !== '' ? 'Измените фильтры или сбросьте поиск.' : 'Журнал пополняется автоматически при исключениях ядра.'" />
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col" class="md-errors-table__level">Уровень</th>
                <th scope="col">Сообщение</th>
                <th scope="col" class="md-errors-table__count">{{ $flat ? 'Запись' : 'Повторы' }}</th>
                <th scope="col" class="md-errors-table__time">{{ $flat ? 'Когда' : 'Последний раз' }}</th>
                <th scope="col" class="md-errors-table__actions"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>
            <x-slot:body>
                @foreach ($flat ? $rows : $groups as $row)
                    @php
                        $id = $flat ? $row['id'] : $row['latest_id'];
                        $time = $flat ? $row['created_at'] : $row['last_at'];
                    @endphp
                    <tr>
                        <td class="md-errors-table__level">
                            <x-md.badge :tone="$levelTones[$row['level']] ?? 'neutral'">{{ $row['level'] }}</x-md.badge>
                        </td>
                        <td class="md-errors-table__message">
                            <div class="md-errors-table__mobile-meta">
                                <x-md.badge :tone="$levelTones[$row['level']] ?? 'neutral'">{{ $row['level'] }}</x-md.badge>
                                <span>{{ $flat ? '#' . $id : $row['count'] . ' повт.' }}</span>
                            </div>
                            <button type="button" class="md-errors-table__open"
                                    title="{{ $row['message'] }}"
                                    yoyo:get="{{ $flat ? 'select' : 'openGroup' }}({{ $id }})">{{ $row['message'] }}</button>
                            <div class="md-errors-table__reference">
                                @if ($row['request_id'] !== '')
                                    <span>Request ID: <code>{{ $row['request_id'] }}</code></span>
                                @else
                                    <span>Request ID не записан</span>
                                @endif
                                <time class="md-errors-table__mobile-time" datetime="{{ str_replace(' ', 'T', $time) }}">{{ $formatTime($time) }}</time>
                            </div>
                        </td>
                        <td class="md-errors-table__count">
                            @if ($flat)
                                <span class="md-table__muted">#{{ $id }}</span>
                            @else
                                <x-md.badge :tone="$row['count'] > 1 ? 'warning' : 'neutral'">{{ $row['count'] }} повт.</x-md.badge>
                            @endif
                        </td>
                        <td class="md-errors-table__time">
                            <time datetime="{{ str_replace(' ', 'T', $time) }}">{{ $formatTime($time) }}</time>
                            @if (!$flat && $row['count'] > 1)
                                <small class="md-errors-table__first">С {{ $formatTime($row['first_at']) }}</small>
                            @endif
                        </td>
                        <td class="md-errors-table__actions">
                            <div class="md-table__actions">
                                @if ($flat && $row['log_file'] !== '')
                                    <x-md.icon-button :href="'/errors/' . $id . '/file'" icon="file-text"
                                                      label="Открыть файл ошибки" size="sm" />
                                @endif
                                <form method="post" action="{{ $flat ? '/errors/' . $id . '/delete' : '/errors/group-delete' }}" class="md-inline-form" yoyo:ignore
                                      data-md-confirm="{{ $flat ? 'Удалить запись об ошибке?' : 'Удалить все записи этой группы?' }}">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    @if (!$flat)
                                        <input type="hidden" name="level" value="{{ $row['level'] }}">
                                        <input type="hidden" name="message" value="{{ $row['message'] }}">
                                    @endif
                                    <x-md.icon-button type="submit" icon="trash" :label="$flat ? 'Удалить запись' : 'Удалить группу'" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
        <x-slot:footer>
            <span>{{ $flat ? 'Нажмите сообщение, чтобы открыть разбор.' : 'Нажмите сообщение, чтобы открыть разбор и повторы.' }}</span>
            <span class="md-table__muted">Поиск по последним 500 записям выбранного уровня.</span>
        </x-slot:footer>
    </x-md.table>
</div>
