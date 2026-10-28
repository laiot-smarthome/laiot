<div class="md-ui-form-stack">
    @if (!$allowed)
        <x-md.alert type="danger" title="Нет доступа" text="Для просмотра и изменения параметров нужно право управления настройками." />
    @else
        @php
            $tabs = [];
            foreach ($groups as $id => $section) {
                $tabs[] = ['id' => $id, 'label' => $section['title'], 'href' => '/settings?group=' . $id];
            }
        @endphp
        <x-md.tabs :items="$tabs" :active="$search === '' ? $group : ''" label="Разделы настроек" />

        <form class="md-ui-search" yoyo:get="findSettings" role="search">
            <x-md.input type="search" name="q" :value="$search" placeholder="Название, описание или ENV-ключ"
                        aria-label="Поиск настроек" autocomplete="off" />
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
            @if ($search !== '')
                <x-md.button :href="'/settings?group=' . $group" variant="ghost">Сбросить</x-md.button>
            @endif
        </form>

        @if ($search !== '')
            <x-md.card :title="'Результаты поиска: ' . count($fields)">
                <div class="md-ui-form-stack">
                    @forelse ($fields as $key => $field)
                        <div>
                            <a href="/settings?group={{ $field['group'] }}#field-{{ $key }}"><strong>{{ $field['label'] }}</strong></a>
                            <span class="md-ui-muted"> · {{ $groups[$field['group']]['title'] }} · <code>{{ $key }}</code></span>
                            <p class="md-ui-muted">{{ $field['description'] }}</p>
                        </div>
                    @empty
                        <x-md.empty-state icon="search" title="Параметры не найдены" text="Попробуйте другое название или ENV-ключ." compact />
                    @endforelse
                </div>
            </x-md.card>
        @elseif ($fields !== [])
            <form class="md-ui-form md-ui-form-stack" yoyo:post="save" autocomplete="off">
                <input type="hidden" name="revision" value="{{ $revision }}">
                <x-md.card :title="$groups[$group]['title']" :subtitle="$groups[$group]['description']">
                    <div class="md-ui-form-stack">
                        @foreach ($fields as $key => $field)
                            @php
                                $hint = $field['description'] . ' Ключ: ' . $key . '. Источник: ' . $field['source'] . '.';
                                if ($field['external']) {
                                    $hint .= ' Значение задано службой или контейнером и имеет приоритет над .env.';
                                }
                            @endphp
                            <div id="field-{{ $key }}">
                                @if ($field['type'] === 'select' || $field['type'] === 'bool')
                                    <x-md.select :name="'values[' . $key . ']'" :id="'setting-' . $key"
                                                 :label="$field['label']" :selected="$field['value']"
                                                 :options="$field['type'] === 'bool' ? ['0' => 'Выключено', '1' => 'Включено'] : $field['options']"
                                                 :searchable="$key === 'APP_TIMEZONE'" :hint="$hint"
                                                 :error="$errors[$key] ?? null" :disabled="$field['external']" required />
                                @elseif ($field['type'] === 'secret')
                                    <x-md.input type="password" :name="'values[' . $key . ']'" :id="'setting-' . $key"
                                                :label="$field['label']" value="" :hint="$hint" autocomplete="new-password"
                                                :placeholder="$field['configured'] ? 'Пароль сохранён' : 'Пароль не задан'"
                                                :disabled="$field['external']" :error="$errors[$key] ?? null" />
                                    <x-md.checkbox :name="'clear[' . $key . ']'" label="Удалить сохранённый пароль"
                                                   :disabled="$field['external']" />
                                @else
                                    <x-md.input :type="$field['type'] === 'int' ? 'number' : 'text'"
                                                :name="'values[' . $key . ']'" :id="'setting-' . $key"
                                                :label="$field['label']" :value="$field['value']" :hint="$hint"
                                                :min="$field['min'] ?? null" :max="$field['max'] ?? null"
                                                :required="$field['required'] ?? ($field['type'] === 'int')"
                                                :disabled="$field['external']" :error="$errors[$key] ?? null" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-md.card>
                <div class="md-ui-form-actions">
                    <x-md.button type="submit" icon="check" yoyo:spinning.attr="disabled">Сохранить раздел</x-md.button>
                    <x-md.button :href="'/settings?group=' . $group" variant="ghost">Отменить изменения</x-md.button>
                </div>
                <p class="md-ui-muted">После изменения параметров перезапустите фоновые процессы. Настройки интеграций находятся в разделе «Модули».</p>
            </form>
        @endif
    @endif
</div>
