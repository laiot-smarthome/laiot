<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Настройки"
        icon="settings"
        :title="$setting['title'] !== '' ? $setting['title'] : $setting['name']"
        :lede="'Параметр ' . $setting['name']"
        :meta="[['icon' => 'list', 'label' => 'Тип', 'value' => $setting['type']]]">
        <x-slot:actions>
            <x-md.button href="/settings" variant="secondary" icon="arrow-left">К настройкам</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <form method="post" action="/settings/{{ $setting['id'] }}" class="md-ui-form">
        <input type="hidden" name="_token" value="{{ $csrf }}">

        <x-md.card title="Значение">
            <div class="md-ui-form-stack">
                @if ($setting['type'] === 'bool' || $setting['type'] === 'boolean')
                    <x-md.select name="value" label="Значение" :selected="$setting['value']">
                        <option value="1" @selected(in_array($setting['value'], ['1', 'true', 'on'], true))>Включено</option>
                        <option value="0" @selected(!in_array($setting['value'], ['1', 'true', 'on'], true))>Выключено</option>
                    </x-md.select>
                @elseif (mb_strlen($setting['value']) > 120 || str_contains($setting['value'], "\n"))
                    <x-md.textarea name="value" label="Значение" :value="$setting['value']" rows="8" />
                @else
                    <x-md.input name="value" label="Значение" :value="$setting['value']" />
                @endif
                <p class="md-ui-muted">Имя: <code>{{ $setting['name'] }}</code> · тип: {{ $setting['type'] }}. Значение читается ядром и сценариями при следующем запросе.</p>
            </div>
        </x-md.card>

        <div class="md-ui-form-actions">
            <x-md.button type="submit" icon="check">Сохранить</x-md.button>
            <x-md.button href="/settings" variant="ghost">Отмена</x-md.button>
        </div>
    </form>
</div>
