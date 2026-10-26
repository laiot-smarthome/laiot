<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Автоматизация"
        icon="book"
        title="База знаний"
        lede="Документация проекта из docs/** читается напрямую: одна и та же для людей и агентов."
        :meta="[
            ['icon' => 'files', 'label' => 'Материалов', 'value' => count($documents)],
            ['icon' => 'folder', 'label' => 'Категорий', 'value' => count($categories)],
        ]" />

    <div class="md-ui-card-grid">
        <form class="md-ui-search" method="get" action="/knowledge-base" role="search">
            <input class="md-input" type="search" name="q" value="{{ $search }}"
                   placeholder="Поиск по документации: заголовок, теги, текст" aria-label="Поиск по базе знаний" autocomplete="off">
            <select class="md-table__filter" name="category" aria-label="Категория" onchange="this.form.submit()">
                <option value="">Все категории</option>
                @foreach ($categories as $key => $item)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $item['label'] }} ({{ $item['count'] }})</option>
                @endforeach
            </select>
            <button class="md-btn md-btn--secondary" type="submit">
                <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
            </button>
            @if ($search !== '' || $category !== '')
                <a class="md-btn md-btn--ghost" href="/knowledge-base">Сбросить</a>
            @endif
        </form>
    </div>

    @if ($documents === [])
        <x-md.empty-state icon="book" title="Материалов нет"
                          :text="$search !== '' || $category !== ''
                            ? 'Измените запрос или сбросьте фильтр.'
                            : 'В docs/ пока нет Markdown-документов с front matter.'" />
    @else
        <div class="md-ui-card-grid">
            @foreach ($documents as $document)
                <a class="md-card md-card--interactive" href="{{ $document['url'] }}">
                    <div class="md-card__body">
                        <div class="md-card__eyebrow">{{ $document['category_label'] }}</div>
                        <h2 class="md-card__title">{{ $document['title'] }}</h2>
                        @if ($document['description'] !== '')
                            <p class="md-card__subtitle">{{ $document['description'] }}</p>
                        @endif
                        <div class="md-ui-actions">
                            @foreach (array_slice($document['tags'], 0, 3) as $tag)
                                <x-md.badge tone="outline">#{{ $tag }}</x-md.badge>
                            @endforeach
                            <span class="md-table__muted">{{ $document['updated_at'] }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
