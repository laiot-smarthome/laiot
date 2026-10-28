@php
    /** @var array<string, mixed> $rule */
    $mode = (string)($rule['mode'] ?? 'none');
    $modes = [
        'none' => 'Без изменений',
        'binary' => 'ON/OFF, true/false ↔ 1/0',
        'exposes' => 'По value_on/value_off из exposes',
        'map' => 'Своя таблица замен',
        'number' => 'Число: масштаб и смещение',
        'invert' => 'Инверсия 1 ↔ 0',
    ];
    $prefixId = $prefix;
@endphp

<div class="md-ui-form-stack">
    <div class="md-ui-form-grid">
        <x-md.select :name="$prefix . '[mode]'" label="Режим" :selected="$mode" :options="$modes" />
        <x-md.input :name="$prefix . '[pairs]'" label="Замены" :value="(string)($rule['pairs'] ?? '')"
                    hint="Одна пара на строку: из=в. Значения читаются как JSON: 1, true, &quot;1&quot;, null различаются." />
    </div>
    <div class="md-ui-form-grid">
        <x-md.input type="number" :name="$prefix . '[factor]'" label="Множитель" :value="(string)($rule['factor'] ?? 1)" />
        <x-md.input type="number" :name="$prefix . '[offset]'" label="Смещение" :value="(string)($rule['offset'] ?? 0)" />
        <x-md.input type="number" :name="$prefix . '[precision]'" label="Округление, знаков" :value="(string)($rule['precision'] ?? '')"
                    hint="Пусто — не округлять. 0–8." />
        <x-md.checkbox :name="$prefix . '[case_sensitive]'" label="Учитывать регистр" value="1"
                       :checked="(bool)($rule['case_sensitive'] ?? true)" />
    </div>
</div>
