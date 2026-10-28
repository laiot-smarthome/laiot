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

<div class="md-errors-list-screen">
    <div class="md-errors-toolbar">
        <div class="md-ui-search">
            <input class="md-input" type="search" name="search" value="{{ $search }}"
                   placeholder="Сообщение, request_id или класс" aria-label="Поиск ошибок"
                   autocomplete="off" yoyo:on="keyup delay:300ms changed">
            <select class="md-table__filter" name="level" aria-label="Уровень ошибки" yoyo>
                <option value="">Все уровни</option>
                @foreach (['error', 'critical', 'warning', 'notice', 'info'] as $levelName)
                    <option value="{{ $levelName }}" @selected($level === $levelName)>{{ $levelName }}</option>
                @endforeach
            </select>
            <button type="button" class="md-btn md-btn--secondary" yoyo:get="toggleFlat">
                <i class="ti ti-{{ $flat ? 'layers' : 'list' }}" aria-hidden="true"></i>
                <span class="md-btn__label">{{ $flat ? 'Группы' : 'Все записи' }}</span>
            </button>
            <button type="button" class="md-btn md-btn--ghost" yoyo:get="reset" aria-label="Сбросить фильтры">
                <i class="ti ti-filter-off" aria-hidden="true"></i><span class="md-btn__label">Сбросить</span>
            </button>
        </div>
        <div class="md-table__meta">
            @if ($message !== '')
                <button type="button" class="md-errors-back" yoyo:get="allGroups">
                    <i class="ti ti-arrow-left" aria-hidden="true"></i> Все группы
                </button>
                <span class="md-errors-muted" title="{{ $message }}">одно сообщение</span>
                <span>·</span>
            @endif
            <span>Групп: {{ count($groups) }}</span>
            <span>·</span>
            <span>записей: {{ count($rows) }}</span>
        </div>
    </div>

    <x-md.card :title="$flat ? 'Записи ошибок' : 'Группы ошибок'"
               :subtitle="$flat
                   ? 'Отдельные записи журнала. Нажмите строку, чтобы открыть разбор.'
                   : 'Одинаковые сообщения собраны в группы. Нажмите группу, чтобы открыть повторы и разбор.'"
               flush>
        <x-slot:actions>
            <x-md.badge tone="neutral">{{ $flat ? count($rows) . ' записей' : count($groups) . ' групп' }}</x-md.badge>
        </x-slot:actions>

        @if ($rows === [])
            <div class="md-errors-list__empty">
                <x-md.empty-state icon="circle-check" :title="$search !== '' || $level !== '' ? 'Ошибок не найдено' : 'Ошибок нет'"
                                  :text="$search !== '' || $level !== '' ? 'Измените фильтры или сбросьте поиск.' : 'Журнал пополняется автоматически при исключениях ядра.'" />
            </div>
        @elseif (!$flat)
            <div class="md-errors-list">
                @foreach ($groups as $group)
                    <div class="md-errors-row">
                        <button type="button" class="md-errors-item" yoyo:get="openGroup({{ $group['latest_id'] }})">
                            <span class="md-errors-item__top">
                                <span class="md-errors-item__badges">
                                    <x-md.badge :tone="$levelTones[$group['level']] ?? 'neutral'">{{ $group['level'] }}</x-md.badge>
                                    <span class="md-errors-item__count">{{ $group['count'] }} повт.</span>
                                </span>
                                <span class="md-errors-item__time">{{ $formatTime($group['last_at']) }}</span>
                            </span>
                            <span class="md-errors-item__message" title="{{ $group['message'] }}">{{ $group['message'] }}</span>
                            <span class="md-errors-item__meta">
                                <span>первый раз: {{ $formatTime($group['first_at']) }}</span>
                                @if ($group['request_id'] !== '')
                                    <code class="md-table__code">{{ $group['request_id'] }}</code>
                                @endif
                            </span>
                        </button>
                        <div class="md-errors-row__actions">
                            <form method="post" action="/errors/group-delete" class="md-inline-form"
                                  data-md-confirm="Удалить все {{ $group['count'] }} записей этой группы?">
                                <input type="hidden" name="_token" value="{{ $csrf }}">
                                <input type="hidden" name="level" value="{{ $group['level'] }}">
                                <input type="hidden" name="message" value="{{ $group['message'] }}">
                                <x-md.icon-button type="submit" icon="trash" label="Удалить группу" variant="danger" size="sm" />
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="md-errors-list">
                @foreach ($rows as $row)
                    <div class="md-errors-row">
                        <button type="button" class="md-errors-item" yoyo:get="select({{ $row['id'] }})">
                            <span class="md-errors-item__top">
                                <span class="md-errors-item__badges">
                                    <x-md.badge :tone="$levelTones[$row['level']] ?? 'neutral'">{{ $row['level'] }}</x-md.badge>
                                    <span class="md-errors-item__count">#{{ $row['id'] }}</span>
                                </span>
                                <span class="md-errors-item__time">{{ $formatTime($row['created_at']) }}</span>
                            </span>
                            <span class="md-errors-item__message" title="{{ $row['message'] }}">{{ $row['message'] }}</span>
                            <span class="md-errors-item__meta">
                                @if ($row['request_id'] !== '')
                                    <code class="md-table__code">{{ $row['request_id'] }}</code>
                                @endif
                            </span>
                        </button>
                        <div class="md-errors-row__actions">
                            @if ($row['log_file'] !== '')
                                <x-md.icon-button :href="'/errors/' . $row['id'] . '/file'" icon="file-text"
                                                  label="Открыть файл ошибки" size="sm" />
                            @endif
                            <form method="post" action="/errors/{{ $row['id'] }}/delete" class="md-inline-form"
                                  data-md-confirm="Удалить запись об ошибке?">
                                <input type="hidden" name="_token" value="{{ $csrf }}">
                                <x-md.icon-button type="submit" icon="trash" label="Удалить запись" variant="danger" size="sm" />
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-md.card>
</div>
