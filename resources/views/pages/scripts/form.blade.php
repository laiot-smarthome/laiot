@php
    $isNew = $script === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $script[$key] ?? '');
    $categoryId = (int)($draft['category_id'] ?? $script['category_id'] ?? 0);
    $eventName = $field('event_name');
    $triggerLabel = $eventName === '' ? 'вручную' : (string)($triggers[$eventName] ?? $eventName);
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
            : 'Код выполняется при запуске, а не при сохранении. Синтаксис проверяется до сохранения.'"
        :meta="$isNew ? [] : [
            ['icon' => 'bolt', 'label' => 'Запуск', 'value' => $triggerLabel],
            ['icon' => 'folder', 'label' => 'Категория', 'value' => $categoryTitle !== '' ? $categoryTitle : 'без категории'],
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
        <x-md.alert type="danger" title="Не удалось сохранить скрипт" :text="$error" />
    @endif

    <form method="post" action="{{ $isNew ? '/scripts/create' : '/scripts/' . $script['id'] . '/edit' }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

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

            <x-md.card title="Запуск" subtitle="Системное событие запускает сценарий так же, как ручной вызов">
                <x-md.input name="event_name" label="Системное событие" :value="$eventName"
                            list="script-system-events" placeholder="system.minute"
                            hint="Пусто — запуск только вручную. Ядро отправляет system.minute, system.hour и system.day; модули могут отправлять свои события system.*." />
                <datalist id="script-system-events">
                    @foreach ($triggers as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </datalist>
            </x-md.card>

            <x-md.card title="Код сценария">
                <x-md.code name="code" :value="$field('code')" :error="$codeError" rows="20"
                           hint="PHP без открывающего тега. Доступны gg(), sg(), cm(), callMethod()." />
            </x-md.card>

            <x-md.card title="Как запустить" icon="player-play"
                      subtitle="Сохранение код не выполняет: сценарий запускается только явно">
                <x-md.hint
                    tone="info"
                    title="Способы запуска"
                    :steps="[
                        'Вручную — из другого сценария через runScript(\'Название\'), из API/webhook или задания очереди.',
                        'По системному событию — ядро запускает сценарий, когда отправляет выбранное событие; в $params приходят event и data.',
                        'Код проверяется перед сохранением: при синтаксической ошибке остаётся только текущая сохранённая версия.',
                    ]" />
            </x-md.card>
        </div>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать скрипт' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/scripts" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
