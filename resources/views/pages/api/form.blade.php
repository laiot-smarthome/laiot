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
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="webhook" /> api · webhook</div>
            <h1 class="md-ui-hero__title">{{ $webhook === null ? 'Новый webhook' : 'Изменить webhook' }}</h1>
            <p class="md-ui-hero__lede">Секрет генерируется на сервере и показывается один раз. В URL передаётся только он.</p>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card :title="$webhook === null ? 'Параметры входа' : $webhook['name']">
        <form method="post"
              action="{{ $webhook === null ? '/module/api' : '/module/api/' . $webhook['id'] }}"
              class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">

            <x-md.input name="name" label="Название" :value="$value('name')" required maxlength="190" />

            <x-md.select name="handler_type" label="Обработчик">
                <option value="script" @selected($handler === 'script')>Сценарий</option>
                <option value="code" @selected($handler === 'code')>PHP-код</option>
            </x-md.select>

            <x-md.select name="script_id" label="Сценарий" hint="Для обработчика «Сценарий».">
                <option value="0">Выберите сценарий</option>
                @foreach ($scripts as $script)
                    <option value="{{ $script['id'] }}" @selected($scriptId === (int)$script['id'])>{{ $script['title'] }}</option>
                @endforeach
            </x-md.select>

            <x-md.textarea name="code" label="PHP-код" rows="8" hint="Без тегов PHP. В массиве params доступны payload, method и headers." :value="$value('code')" />

            <div class="md-field">
                <span class="md-field__label">HTTP-методы</span>
                <div class="md-ui-actions">
                    @foreach ($methods as $method)
                        <x-md.checkbox :name="'methods[]'" :value="$method" :label="$method" :checked="in_array($method, $selectedMethods, true)" />
                    @endforeach
                </div>
            </div>

            <x-md.switch name="enabled" value="1" label="Включён" :checked="$enabled" />

            <div class="md-ui-actions">
                <x-md.button type="submit" icon="check">Сохранить</x-md.button>
                <x-md.button href="/module/api" variant="ghost">Отмена</x-md.button>
            </div>
        </form>
    </x-md.card>

    @if ($webhook !== null)
        <x-md.hint tone="warning" title="Секрет" text="Секрет нельзя посмотреть повторно. Если он потерян — перевыпустите его в списке." />
    @endif
</div>
