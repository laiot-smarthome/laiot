@php
    /** @var list<array<string, mixed>> $bridges */
    $editing = $editing ?? null;
    $events = $events['rows'] ?? [];
    $human = static function (string $value): string {
        if ($value === '') {
            return '—';
        }
        $ts = strtotime($value);

        return $ts === false ? '—' : date('d.m.Y H:i:s', $ts);
    };
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Интеграции · Zigbee"
        icon="router"
        title="Экземпляры Zigbee2MQTT"
        lede="Корневой топик, состояние и команды bridge/request. Все экземпляры используют соединение, авторизацию и TLS модуля MQTT."
        :meta="[
            ['icon' => 'network', 'label' => 'Устройств', 'value' => (string)($counts['total'] ?? 0)],
            ['icon' => 'circle-check', 'label' => 'Добавлено', 'value' => (string)($counts['added'] ?? 0)],
        ]">
        <x-slot:actions>
            <x-md.button href="/module/zigbee" variant="secondary" icon="arrow-left">К устройствам</x-md.button>
            <x-md.button href="/module/zigbee/journal" variant="ghost" icon="history">Журнал</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <form method="post" action="{{ $editing !== null ? '/module/zigbee/bridges/' . (int)$editing['id'] : '/module/zigbee/bridges' }}" class="md-card">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">{{ $editing !== null ? 'Редактирование' : 'Новый экземпляр' }}</div>
                <h2 class="md-card__title">{{ $editing !== null ? $editing['title'] : 'Подключение Zigbee2MQTT' }}</h2>
                <p class="md-card__subtitle">Корень существующего экземпляра не меняется: для другого корня создайте новый.</p>
            </div>
        </header>
        <div class="md-card__body">
            <div class="md-ui-form-grid">
                <x-md.input name="title" label="Название" :value="(string)($editing['title'] ?? '')" placeholder="Zigbee2MQTT" required />
                <x-md.input name="base_topic" label="Корневой топик" :value="(string)($editing['base_topic'] ?? '')"
                            placeholder="zigbee2mqtt" required
                            hint="Без /# и подстановочных знаков. Корни экземпляров не должны пересекаться." />
            </div>
            <x-md.switch name="enabled" label="Экземпляр включён" :checked="(bool)($editing['enabled'] ?? true)"
                         description="Выключенный экземпляр не принимает сообщения и не отправляет команды." />
            <div class="md-ui-actions">
                <x-md.button type="submit" variant="primary" icon="device-floppy">{{ $editing !== null ? 'Сохранить' : 'Создать экземпляр' }}</x-md.button>
                @if ($editing !== null)
                    <x-md.button href="/module/zigbee/bridges" variant="ghost" icon="x">Отмена</x-md.button>
                @endif
            </div>
        </div>
    </form>

    <section class="md-card">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Экземпляры</div>
                <h2 class="md-card__title">Список</h2>
            </div>
        </header>
        @if ($bridges === [])
            <div class="md-card__body">
                <x-md.empty-state icon="router" title="Экземпляров нет" text="Добавьте корневой топик, например zigbee2mqtt." compact />
            </div>
        @else
            <div class="md-table-wrap">
                <div class="md-table__scroll">
                    <table class="md-table">
                        <thead>
                        <tr>
                            <th>Экземпляр</th>
                            <th>Состояние</th>
                            <th>Последняя связь</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($bridges as $bridge)
                            <tr>
                                <td>
                                    <strong>{{ $bridge['title'] }}</strong>
                                    <div class="md-table__muted"><code class="md-table__code">{{ $bridge['base_topic'] }}</code></div>
                                </td>
                                <td>
                                    <x-md.badge :tone="(string)$bridge['state'] === 'online' ? 'success' : ((string)$bridge['state'] === 'offline' ? 'danger' : 'neutral')" dot>
                                        {{ $bridge['state'] }}
                                    </x-md.badge>
                                    <x-md.badge :tone="(int)$bridge['enabled'] === 1 ? 'info' : 'neutral'">
                                        {{ (int)$bridge['enabled'] === 1 ? 'включён' : 'выключен' }}
                                    </x-md.badge>
                                </td>
                                <td>{{ $human((string)($bridge['last_seen'] ?? '')) }}</td>
                                <td>
                                    <div class="md-ui-actions">
                                        <x-md.button :href="'/module/zigbee/bridges?edit=' . (int)$bridge['id']" variant="ghost" size="sm" icon="pencil">Изменить</x-md.button>
                                        <form method="post" action="/module/zigbee/bridges/{{ (int)$bridge['id'] }}/delete" class="md-inline-form"
                                              data-md-confirm="Удалить экземпляр, его устройства и привязки?">
                                            <input type="hidden" name="_token" value="{{ $csrf }}">
                                            <x-md.button type="submit" variant="danger" size="sm" icon="trash">Удалить</x-md.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <form method="post" action="/module/zigbee/bridges/{{ (int)$bridge['id'] }}/command" class="md-ui-form-grid">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.select name="command" label="Команда Zigbee2MQTT"
                                                     :options="[
                                                        'permit_join' => 'Разрешить подключение',
                                                        'device/rename' => 'Переименовать устройство',
                                                        'device/remove' => 'Удалить из сети',
                                                        'device/configure' => 'Настроить',
                                                        'device/interview' => 'Повторное интервью',
                                                        'device/options' => 'Параметры устройства',
                                                        'health_check' => 'Проверка состояния',
                                                        'networkmap' => 'Карта сети',
                                                     ]" />
                                        <x-md.textarea name="params" label="JSON-параметры" rows="2" value=""
                                                       placeholder='{"id":"0x00158d000237212e"}'
                                                       hint="Объект параметров команды; transaction добавляется автоматически." />
                                        <div class="md-ui-actions">
                                            <x-md.button type="submit" variant="secondary" icon="send" size="sm">Отправить</x-md.button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    <section class="md-card">
        <header class="md-card__head">
            <div class="md-card__titles">
                <div class="md-card__eyebrow">Диагностика</div>
                <h2 class="md-card__title">Последние события</h2>
            </div>
            <x-md.button href="/module/zigbee/journal" variant="ghost" icon="history">Весь журнал</x-md.button>
        </header>
        <div class="md-card__body">
            @if ($events === [])
                <p class="md-table__muted">Записей пока нет.</p>
            @else
                <div class="md-ui-form-stack">
                    @foreach ($events as $event)
                        <div class="md-ui-actions">
                            <x-md.badge :tone="(string)$event['level'] === 'error' ? 'danger' : ((string)$event['level'] === 'warning' ? 'warning' : 'info')">
                                {{ $event['level'] }}
                            </x-md.badge>
                            <strong>{{ $event['topic'] }}</strong>
                            <span class="md-table__muted">{{ $event['message'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
