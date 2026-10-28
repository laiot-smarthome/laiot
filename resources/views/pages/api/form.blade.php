@php
    $value = static fn (string $key, string $default = ''): string => (string)($draft[$key] ?? $webhook[$key] ?? $default);
    $handler = $value('handler_type', 'script');
    $scriptId = (int)($draft['script_id'] ?? $webhook['script_id'] ?? 0);
    $selectedMethods = $draft['methods'] ?? $webhook['methods'] ?? ['POST'];
    $selectedMethods = is_array($selectedMethods) ? $selectedMethods : [$selectedMethods];
    $enabled = array_key_exists('enabled', $draft)
        ? !empty($draft['enabled'])
        : (bool)($webhook['enabled'] ?? true);
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$webhook === null ? 'Новый webhook' : 'Карточка webhook'"
        icon="webhook"
        :title="$webhook === null ? 'Новый webhook' : $webhook['name']"
        lede="Секрет генерируется на сервере и показывается один раз; в URL передаётся только он."
        :meta="$webhook === null ? [] : [
            ['icon' => 'link', 'label' => 'ID', 'value' => '#' . $webhook['id']],
        ]">
        <x-slot:actions>
            <x-md.button href="/api" variant="secondary" icon="arrow-left">К webhook</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($error !== null)
        <x-md.notify type="danger" title="Не удалось сохранить webhook" :text="$error" />
    @endif

    <form method="post" action="{{ $webhook === null ? '/api' : '/api/' . $webhook['id'] }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Параметры входа">
            <div class="md-ui-form-stack">
                <div class="md-ui-form-grid">
                    <x-md.input name="name" label="Название" :value="$value('name')" required maxlength="190"
                                hint="Например: «Уведомление от камеры»." />
                    <x-md.select name="handler_type" label="Обработчик" :selected="$handler">
                        <option value="script" @selected($handler === 'script')>Сценарий</option>
                        <option value="code" @selected($handler === 'code')>PHP-код</option>
                    </x-md.select>
                    <x-md.select name="script_id" label="Сценарий" :selected="$scriptId" hint="Только для обработчика «Сценарий».">
                        <option value="0">Выберите сценарий</option>
                        @foreach ($scripts as $script)
                            <option value="{{ $script['id'] }}" @selected($scriptId === (int)$script['id'])>{{ $script['title'] }}</option>
                        @endforeach
                    </x-md.select>
                </div>

                <x-md.textarea name="code" label="PHP-код" rows="8" :value="$value('code')"
                               hint="Без тегов PHP. В массиве params доступны payload, method и headers." />

                <div class="md-field">
                    <span class="md-field__label">Разрешённые HTTP-методы</span>
                    <div class="md-field__control">
                        <div class="md-ui-actions">
                            @foreach ($methods as $method)
                                <x-md.checkbox :name="'methods[]'" :value="$method" :label="$method"
                                               :checked="in_array($method, $selectedMethods, true)" />
                            @endforeach
                        </div>
                    </div>
                </div>

                <x-md.switch name="enabled" value="1" label="Webhook включён"
                             description="Выключенный вход отвечает отказом, не запуская обработчик."
                             :checked="$enabled" />
            </div>
        </x-md.card>

        @if ($webhook !== null)
            <x-md.hint tone="warning" title="Секрет нельзя посмотреть повторно"
                       text="Если секрет потерян, перевыпустите его в списке webhook — старый сразу перестанет работать." />
        @endif

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">{{ $webhook === null ? 'Создать webhook' : 'Сохранить' }}</x-md.button>
            <x-md.button href="/api" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
