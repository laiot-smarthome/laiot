<div class="md-kb">
    <x-md.card class="md-kb__search-card">
        <form class="md-kb__search" role="search" yoyo:get="render">
            <x-md.input type="search" name="search" id="kb-search" :value="$search"
                        label="Поиск по базе знаний" icon="search"
                        placeholder="Например, MQTT, сценарии или свойства"
                        autocomplete="off" yoyo yoyo:on="input delay:300ms changed" />
            <x-md.button type="submit" icon="search" yoyo:spinning.attr="disabled">Найти</x-md.button>
            @if ($search !== '' || $category !== '')
                <x-md.button variant="ghost" icon="filter-off" yoyo:get="reset">Сбросить</x-md.button>
            @endif
        </form>
        <div class="md-kb__search-status">
            <p class="md-kb__help">Ищем по заголовкам, тегам и полному тексту статей.</p>
            <span yoyo:spinning.delay role="status"><span class="md-kb__loading"><span class="md-spinner" aria-hidden="true"></span> Ищем материалы…</span></span>
        </div>
    </x-md.card>

    <div class="md-kb__layout">
        <aside class="md-kb__sidebar">
            <x-md.card title="Категории" icon="folders" :heading-level="2" compact>
                <nav class="md-kb__categories" aria-label="Категории базы знаний">
                    <button type="button" class="md-kb__category" yoyo:get="chooseCategory('')"
                            aria-pressed="{{ $category === '' ? 'true' : 'false' }}">
                        <span>Все материалы</span><x-md.badge tone="neutral">{{ $total }}</x-md.badge>
                    </button>
                    @foreach ($categories as $key => $item)
                        <button type="button" class="md-kb__category" yoyo:get="chooseCategory('{{ base64_encode($key) }}')"
                                aria-pressed="{{ $category === $key ? 'true' : 'false' }}">
                            <span>{{ $item['label'] }}</span><x-md.badge tone="neutral">{{ $item['count'] }}</x-md.badge>
                        </button>
                    @endforeach
                </nav>
            </x-md.card>
        </aside>

        <section class="md-kb__results" aria-labelledby="kb-results-title">
            <header class="md-kb__results-head">
                <h2 id="kb-results-title">{{ $categories[$category]['label'] ?? ($category === '' ? 'Все материалы' : 'Категория не найдена') }}</h2>
                <span class="md-kb__help" role="status" aria-live="polite">Найдено: {{ count($documents) }}</span>
            </header>
            @if ($documents === [])
                <x-md.card>
                    <x-md.empty-state icon="search" title="Материалы не найдены"
                                      text="Попробуйте другие ключевые слова или сбросьте фильтры, чтобы увидеть все статьи." />
                    @if ($search !== '' || $category !== '')
                        <x-md.button variant="secondary" icon="filter-off" yoyo:get="reset">Показать все материалы</x-md.button>
                    @endif
                </x-md.card>
            @else
                <div class="md-kb__grid">
                    @foreach ($documents as $document)
                        <x-md.card class="md-kb__document" interactive>
                            <div class="md-kb__document-category"><x-md.icon name="file-text" /> {{ $document['category_label'] }}</div>
                            <h3 class="md-kb__title"><a href="{{ $document['url'] }}">{{ $document['title'] }}</a></h3>
                            @if ($document['description'] !== '')
                                <p class="md-kb__description">{{ $document['description'] }}</p>
                            @endif
                            <div class="md-ui-actions md-kb__tags">
                                @foreach (array_slice($document['tags'], 0, 3) as $tag)
                                    <x-md.badge tone="outline">{{ $tag }}</x-md.badge>
                                @endforeach
                            </div>
                            <div class="md-kb__updated"><x-md.icon name="clock" /> Обновлено {{ (new \DateTimeImmutable($document['updated_at']))->format('d.m.Y H:i') }}</div>
                        </x-md.card>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
