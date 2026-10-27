@php
    $isNew = $script === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $script[$key] ?? '');
    $categoryId = (int)($draft['category_id'] ?? $script['category_id'] ?? 0);
    $eventName = $field('event_name');
    $triggerLabel = $eventName === '' ? 'вручную' : (string)($triggers[$eventName] ?? $eventName);
    $useCore = $draft === null
        ? (bool)($script['use_core'] ?? true)
        : (string)($draft['use_core'] ?? '0') === '1';
    $categoryTitle = '';
    foreach ($categories as $category) {
        if ($category['id'] === $categoryId) {
            $categoryTitle = (string)$category['title'];
        }
    }
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый скрипт' : 'Карточка скрипта'"
        icon="file-code"
        :title="$isNew ? 'Новый скрипт' : $script['title']"
        :lede="$isNew
            ? 'Сценарий выполняется в фоне: он может менять значения, вызывать методы и писать в журнал.'
            : 'Код выполняется при запуске, а не при сохранении. Кнопка «Выполнить» запускает черновик и показывает результат.'"
        :meta="$isNew ? [] : [
            ['icon' => 'bolt', 'label' => 'Запуск', 'value' => $triggerLabel],
            ['icon' => 'folder', 'label' => 'Категория', 'value' => $categoryTitle !== '' ? $categoryTitle : 'без категории'],
            ['icon' => 'terminal', 'label' => 'Ядро', 'value' => $useCore ? 'подключено' : 'не подключено'],
        ]">
        <x-slot:actions>
            <x-md.button href="/scripts" variant="secondary" icon="arrow-left">К скриптам</x-md.button>
            @if (!$isNew)
                <form method="post" action="/scripts/{{ $script['id'] }}/delete" class="md-inline-form"
                      data-md-confirm="Удалить скрипт «{{ $script['title'] }}»?">
                    <input type="hidden" name="_token" value="{{ $csrf }}">
                    <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
                </form>
            @endif
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.alert type="danger" title="Действие не выполнено" :text="$error" />
    @endif

    <form method="post" action="{{ $isNew ? '/scripts/create' : '/scripts/' . $script['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">
        <input type="hidden" name="id" value="{{ $isNew ? 0 : $script['id'] }}">

        <div class="md-ui-form-stack">
            <x-md.card title="Параметры скрипта">
                <div class="md-ui-form-stack">
                    <div class="md-ui-form-grid">
                        <x-md.input name="title" label="Название" :value="$field('title')" required
                                    hint="Как сценарий будет виден в списке и вызываться из кода." />
                        <x-md.select name="category_id" label="Категория" :selected="$categoryId">
                            <option value="0">Без категории</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category['id'] }}" @selected($categoryId === $category['id'])>{{ $category['title'] }}</option>
                            @endforeach
                        </x-md.select>
                    </div>
                    <x-md.textarea name="description" label="Описание" :value="$field('description')" rows="2"
                                   hint="Что делает сценарий и когда его запускать." />
                </div>
            </x-md.card>

            <x-md.card title="Запуск" subtitle="Событие запускает сценарий так же, как ручной вызов">
                <x-md.input name="event_name" label="Событие" :value="$eventName"
                            list="script-events" placeholder="system.minute"
                            hint="Пусто — запуск только вручную. Ядро отправляет system.minute, system.hour и system.day; модули — module.<id>.*, например module.mqtt.ready." />
                <datalist id="script-events">
                    @foreach ($triggers as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </datalist>
            </x-md.card>

            <x-md.card title="Код сценария">
                <div class="md-ui-form-stack">
                    <input type="hidden" name="use_core" value="0">
                    <x-md.code name="code" :value="$field('code')" :error="$codeError" rows="20"
                               hint="PHP без открывающего тега. С включённым ядром доступны gg(), sg(), cm(), callMethod()." />
                    <x-md.checkbox name="use_core" value="1" label="Подключать ядро"
                                   description="По умолчанию включено: доступны обёртки ядра и $params. Если выключить, код выполнится отдельным php-процессом как обычный файл — функции ядра будут недоступны."
                                   :checked="$useCore" />
                </div>
            </x-md.card>

            @if ($runResult !== null)
                <x-md.card title="Результат выполнения" icon="player-play"
                          :subtitle="$runResult['ok'] ? 'Сценарий выполнен синхронно.' : 'Сценарий завершился ошибкой.'">
                    <div class="md-ui-form-stack">
                        <div class="md-ui-actions">
                            <x-md.badge :tone="$runResult['ok'] ? 'success' : 'danger'">
                                {{ $runResult['ok'] ? 'Выполнено' : 'Ошибка' }}
                            </x-md.badge>
                            <span class="md-table__muted">{{ $runResult['duration_ms'] }} мс</span>
                        </div>
                        @if ($runResult['error'] !== '')
                            <x-md.alert type="danger" :text="$runResult['error']" />
                        @endif
                        @if ($runResult['output'] !== '')
                            <div>
                                <div class="md-field__label">Вывод</div>
                                <pre class="md-run-result">{{ $runResult['output'] }}</pre>
                            </div>
                        @endif
                        @if ($runResult['result'] !== '')
                            <div>
                                <div class="md-field__label">Результат</div>
                                <pre class="md-run-result">{{ $runResult['result'] }}</pre>
                            </div>
                        @endif
                        @if ($runResult['ok'] && $runResult['output'] === '' && $runResult['result'] === '')
                            <p class="md-table__muted">Сценарий выполнен, вывода и возвращённого значения нет.</p>
                        @endif
                    </div>
                </x-md.card>
            @endif

            <x-md.card title="Как запустить" icon="player-play"
                      subtitle="Сохранение код не выполняет: сценарий запускается только явно">
                <x-md.hint
                    tone="info"
                    title="Способы запуска"
                    :steps="[
                        'Здесь — кнопка «Выполнить»: код запускается синхронно, без сохранения, и показывает вывод, результат и ошибку.',
                        'Из другого сценария — runScript(\'Название\'), из API/webhook или задания очереди.',
                        'По событию — ядро или модуль отправляет выбранное событие, в $params приходят event и data.',
                        'Код проверяется перед сохранением: при синтаксической ошибке остаётся только текущая сохранённая версия.',
                    ]" />
            </x-md.card>
        </div>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" variant="secondary" icon="player-play"
                         formaction="/scripts/run" formnovalidate>Выполнить</x-md.button>
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать скрипт' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/scripts" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
