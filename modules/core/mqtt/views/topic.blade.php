@php
    $path = (string)($topic['path'] ?? '');
    $title = (string)($topic['title'] ?? '');
    $pathWrite = (string)($topic['path_write'] ?? '');
    $value = (string)($topic['value'] ?? '');
    $updated = (string)($topic['updated'] ?? '');
    $preview = static function (string $text, int $limit = 400): string {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit, 'UTF-8') . '…';
    };
    $human = static function (string $timestamp): string {
        if ($timestamp === '') {
            return '—';
        }
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'только что';
        }
        if ($diff < 86400) {
            return date('H:i:s', $ts);
        }

        return date('d.m.Y H:i', $ts);
    };
    $locationOptions = [];
    foreach ($locations as $location) {
        $locationOptions[(string)$location['id']] = $location['title'];
    }
    $locationOptions['0'] = 'Без локации';
    $linkedObject = (string)($topic['linked_object'] ?? '');
    $linkedProperty = (string)($topic['linked_property'] ?? '');
    $linkedMethod = (string)($topic['linked_method'] ?? '');
@endphp

<div class="md-ui-page md-ui-page--stack md-mqtt-page md-mqtt-edit-page">
    <header class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="radio" />MQTT · топик</div>
            <h1 class="md-ui-hero__title">{{ $title !== '' && $title !== $path ? $title : ($path !== '' ? $path : 'Новый топик') }}</h1>
            @if ($path !== '')
                <p class="md-ui-hero__lede"><code class="md-table__code">{{ $path }}</code></p>
            @endif
            <div class="md-ui-hero__meta">
                <span class="md-ui-hero__meta-item">
                    <x-md.icon name="message" /><span>Значение</span>
                    <strong title="{{ $preview($value, 1000) }}">{{ $value === '' ? '—' : $preview($value, 90) }}</strong>
                </span>
                <span class="md-ui-hero__meta-item"><x-md.icon name="clock" /><span>Обновлено</span><strong>{{ $human($updated) }}</strong></span>
                @if ($pathWrite !== '')
                    <span class="md-ui-hero__meta-item"><x-md.icon name="send" /><span>Команда</span><strong>{{ $pathWrite }}</strong></span>
                @endif
                @if ($linkedObject !== '')
                    <span class="md-ui-hero__meta-item"><x-md.icon name="link" /><span>Привязка</span><strong>{{ $linkedObject }}{{ $linkedProperty !== '' ? '.' . $linkedProperty : ($linkedMethod !== '' ? '.' . $linkedMethod : '') }}</strong></span>
                @endif
            </div>
        </div>
        <div class="md-ui-hero__actions">
            <x-md.button href="/module/mqtt" variant="secondary" icon="arrow-left">К топикам</x-md.button>
            @if (!$isNew)
                <form method="post" action="/module/mqtt/topics/{{ $id }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить топик и его историю?">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </div>
    </header>

    @if ($done !== '')
        <x-md.notify type="success" :text="$done" />
    @endif
    @if ($error !== '')
        <x-md.notify type="danger" title="Топик не сохранён" :text="$error" />
    @endif

    <form method="post" action="{{ $isNew ? '/module/mqtt/topics' : '/module/mqtt/topics/' . $id }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <div class="md-ui-form-stack">
            <section class="md-card">
                <header class="md-card__head">
                    <div class="md-card__titles">
                        <div class="md-card__eyebrow">Направление</div>
                        <h2 class="md-card__title">Топик и команда</h2>
                        <p class="md-card__subtitle">Путь состояния из брокера и, если нужно, отдельный путь команды.</p>
                    </div>
                </header>
                <div class="md-card__body">
                    <div class="md-ui-form-stack">
                        <x-md.input name="path" label="Путь топика" :value="$path" required
                                    placeholder="home/living/temperature"
                                    hint="MQTT-путь без подстановочных знаков. Используется для приёма значения." />
                        <div class="md-ui-form-grid">
                            <x-md.input name="title" label="Название" :value="$title" placeholder="Температура в гостиной"
                                        hint="Подпись в списке. Пусто — последний сегмент пути." />
                            <x-md.input name="path_write" label="Топик команды" :value="$pathWrite" placeholder="home/living/set"
                                        hint="Необязательно. Если пусто, команда уходит в тот же путь." />
                        </div>
                        <div class="md-ui-form-grid">
                            <x-md.select name="location_id" label="Локация" :options="$locationOptions"
                                         :selected="(string)($topic['location_id'] ?? 0)" />
                            <x-md.select name="qos" label="QoS" :selected="(string)($topic['qos'] ?? 0)" :options="[
                                '0' => '0 — без подтверждения (быстро)',
                                '1' => '1 — с подтверждением',
                                '2' => '2 — ровно один раз',
                            ]" />
                        </div>
                        <x-md.checkbox name="retain" label="Retain" :checked="(bool)($topic['retain'] ?? false)"
                                       description="Брокер хранит последнее значение и отдаёт его новым подписчикам." />
                    </div>
                </div>
            </section>

            <section class="md-card">
                <header class="md-card__head">
                    <div class="md-card__titles">
                        <div class="md-card__eyebrow">Привязка</div>
                        <h2 class="md-card__title">Объект, свойство или метод</h2>
                        <p class="md-card__subtitle">Входящее значение пишется в свойство и/или вызывает метод; изменение свойства публикует команду.</p>
                    </div>
                </header>
                <div class="md-card__body">
                    <x-md.linked-object
                        name="linked_object"
                        :value="$linkedObject"
                        label="Объект"
                        propertyName="linked_property"
                        :property="$linkedProperty"
                        methodName="linked_method"
                        :method="$linkedMethod"
                        placeholder="Начните вводить имя объекта"
                        hint="Очистите объект, чтобы снять привязку. Подписка на свойство ставится автоматически." />
                </div>
            </section>

            <section class="md-card">
                <header class="md-card__head">
                    <div class="md-card__titles">
                        <div class="md-card__eyebrow">Поведение</div>
                        <h2 class="md-card__title">Фильтры и журнал</h2>
                        <p class="md-card__subtitle">Повторы, замена значений и запись истории по этому топику.</p>
                    </div>
                </header>
                <div class="md-card__body">
                    <div class="md-ui-form-stack">
                        <x-md.checkbox name="readonly" label="Только чтение" :checked="(bool)($topic['readonly'] ?? false)"
                                       description="Изменение связанного свойства не отправляет команду в брокер." />
                        <x-md.checkbox name="only_new_value" label="Принимать только новые значения"
                                       :checked="(bool)($topic['only_new_value'] ?? false)"
                                       description="Повтор того же значения не пишет свойство и не запускает OnChange." />
                        <x-md.checkbox name="logging" label="Писать историю" :checked="(bool)($topic['logging'] ?? false)"
                                       description="Входящие и исходящие сообщения сохраняются в журнал топика." />
                        <x-md.input name="replace_list" label="Замены значений" :value="(string)($topic['replace_list'] ?? '')"
                                    placeholder="ON=1,OFF=0"
                                    hint="Пары новое=старое через запятую. Входящее «новое» станет «старым», исходящее — наоборот." />
                    </div>
                </div>
            </section>

            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать топик' : 'Сохранить' }}</x-md.button>
                <x-md.button href="/module/mqtt" variant="secondary">Отмена</x-md.button>
            </div>
        </div>
    </form>

    @if (!$isNew)
        <div class="md-mqtt-edit-layout">
            <div class="md-mqtt-edit-main">
                <section class="md-card">
                    <header class="md-card__head">
                        <div class="md-card__titles">
                            <div class="md-card__eyebrow">Ручная команда</div>
                            <h2 class="md-card__title">Опубликовать значение</h2>
                            <p class="md-card__subtitle">Команда уходит в <code>{{ $pathWrite !== '' ? $pathWrite : $path }}</code> через то же соединение и очередь, что и автоматизация.</p>
                        </div>
                    </header>
                    <div class="md-card__body">
                        <form method="post" action="/module/mqtt/topics/{{ $id }}/publish" class="md-ui-form">
                            <input type="hidden" name="_token" value="{{ $csrf }}">
                            <div class="md-ui-actions">
                                <input class="md-input md-mqtt-publish-input" type="text" name="value" value="{{ $value }}"
                                       placeholder="Значение команды" aria-label="Значение команды" autocomplete="off">
                                <x-md.button type="submit" variant="secondary" icon="send">Отправить</x-md.button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="md-card">
                    <header class="md-card__head">
                        <div class="md-card__titles">
                            <div class="md-card__eyebrow">Журнал</div>
                            <h2 class="md-card__title">Последние сообщения</h2>
                        </div>
                    </header>
                    <div class="md-card__body">
                        @if ($history === [])
                            <x-md.empty-state icon="history" title="Записей нет" text="Включите «Писать историю» или дождитесь сообщений брокера." compact />
                        @else
                            <div class="md-table__scroll">
                                <table class="md-table">
                                    <thead>
                                    <tr><th>Направление</th><th>Значение</th><th>Payload</th><th>Привязка</th><th>Время</th></tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($history as $row)
                                        @php $out = (int)$row['direction'] === 1; @endphp
                                        <tr>
                                            <td>
                                                <x-md.badge :tone="$out ? 'info' : 'success'">{{ $out ? 'исходящее' : 'входящее' }}</x-md.badge>
                                            </td>
                                            <td><code class="md-table__value">{{ $preview((string)$row['value'], 120) }}</code></td>
                                            <td><code class="md-table__code">{{ $preview((string)$row['payload'], 120) }}</code></td>
                                            <td class="md-table__muted">{{ $row['linked_data'] !== '' ? $row['linked_data'] : '—' }}</td>
                                            <td class="md-table__muted" title="{{ $row['created_at'] }}">{{ $human((string)$row['created_at']) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            </div>

            <aside class="md-mqtt-edit-sidebar">
                <section class="md-card">
                    <header class="md-card__head">
                        <div class="md-card__titles">
                            <div class="md-card__eyebrow">Ветка</div>
                            <h2 class="md-card__title">Дочерние топики</h2>
                        </div>
                    </header>
                    <div class="md-card__body">
                        @if ($children === [])
                            <p class="md-table__muted">Дочерних топиков нет.</p>
                        @else
                            <ul class="md-mqtt-children">
                                @foreach ($children as $child)
                                    <li>
                                        <a href="/module/mqtt/topics/{{ (int)$child['id'] }}/edit">{{ $child['path'] }}</a>
                                        <span class="md-table__muted">{{ $preview((string)$child['value'], 60) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>

                <x-md.hint
                    tone="tip"
                    title="Как это работает"
                    :steps="[
                        'Входящее сообщение разбирается в процессе worker mqtt и пишет значение свойства без ожидания панели.',
                        'Изменение свойства приходит в очередь module.mqtt; worker отправляет команду через открытое соединение.',
                        'Собственное эхо входящего значения обратно не публикуется, поэтому цикл команд не возникает.',
                    ]" />
            </aside>
        </div>
    @endif
</div>
