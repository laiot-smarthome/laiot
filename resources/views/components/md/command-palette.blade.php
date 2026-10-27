@props(['id', 'items' => []])
@php
    // Палитра принимает только локальные ссылки и не исполняет произвольные URL.
    $commands = array_values(array_filter($items, static fn ($item) => is_array($item) && isset($item['label'], $item['href']) && is_string($item['href']) && (str_starts_with($item['href'], '#') || (str_starts_with($item['href'], '/') && !str_starts_with($item['href'], '//')))));
@endphp
<x-md.modal :id="$id" title="Командная палитра">
    <div x-data="mdCommandPalette(@js($commands))">
        <label class="md-field__label" for="{{ $id }}-search">Поиск команды</label>
        <input id="{{ $id }}-search" class="md-input" type="search" placeholder="Найти действие или раздел" x-model="query" @input="active = 0" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="run()" autocomplete="off">
        <div class="md-kit-command-list" aria-label="Результаты поиска">
            <template x-for="(item, index) in matches" :key="item.href + index">
                <a :href="item.href" :class="{'is-active': index === active}" @mouseenter="active = index"><strong x-text="item.label"></strong><small x-text="item.description || ''"></small></a>
            </template>
            <p x-cloak x-show="matches.length === 0">Ничего не найдено</p>
        </div>
    </div>
</x-md.modal>
