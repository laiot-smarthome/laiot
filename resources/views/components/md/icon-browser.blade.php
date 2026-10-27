<div {{ $attributes->class(['md-kit-icons']) }} x-data="mdIconBrowser">
    <label class="md-field__label" for="md-icon-search">Поиск иконки</label>
    <input id="md-icon-search" class="md-input" type="search" placeholder="Например, home" x-model.debounce.150ms="query" @input="limit = 96">
    <p class="md-table__muted" x-text="error || (icons.length ? 'Найдено: ' + matches.length : 'Загрузка каталога…')" role="status"></p>
    <div class="md-kit-icons__grid">
        <template x-for="name in matches.slice(0, limit)" :key="name">
            <button type="button" class="md-kit-icons__item" @click="copy(name)" :aria-label="'Скопировать ti ti-' + name" :title="'ti ti-' + name"><i :class="'ti ti-' + name" aria-hidden="true"></i><span x-text="name"></span></button>
        </template>
    </div>
    <button type="button" class="md-btn md-btn--secondary" x-cloak x-show="matches.length > limit" @click="limit += 96">Показать ещё</button>
    <p class="md-table__muted" role="status" x-text="copied"></p>
</div>
