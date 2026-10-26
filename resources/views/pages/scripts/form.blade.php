@php
    $isNew = $script === null;
    $field = static fn (string $key): string => (string)($draft[$key] ?? $script[$key] ?? '');
    $categoryId = (int)($draft['category_id'] ?? $script['category_id'] ?? 0);
    $periodic = (bool)($draft['run_periodically'] ?? $script['run_periodically'] ?? false);
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$isNew ? 'Новый скрипт' : 'Карточка скрипта'"
        icon="file-code"
        :title="$isNew ? 'Новый скрипт' : $script['title']"
        :lede="$isNew ? 'Сценарий выполняется в фоне: он может менять значения, вызывать методы и писать в журнал.' : 'Код выполняется при запуске, а не при сохранении.'"
        :meta="$isNew ? [] : [
            ['icon' => 'bolt', 'label' => 'Запуск', 'value' => $periodic ? 'по расписанию' : 'вручную'],
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
                <x-md.checkbox name="run_periodically" value="1" label="Запускать по расписанию"
                               description="Сценарий попадёт в общий цикл запуска, а не только в ручной вызов."
                               :checked="$periodic" />
            </div>
        </x-md.card>

        <x-md.card title="Код сценария">
            <x-md.code name="code" :value="$field('code')" rows="20"
                       hint="PHP без открывающего тега. Доступны gg(), sg(), cm(), callMethod()." />
            <x-md.hint tone="info" title="Как запустить"
                       text="Вручную — из сценария через runScript('Название'), из API или командой запуска заданий. Сохранение код не выполняет." />
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $isNew ? 'Создать скрипт' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/scripts" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
