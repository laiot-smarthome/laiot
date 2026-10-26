<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="book" /> core · knowledge_base</div>
            <h1 class="md-ui-hero__title">База знаний</h1>
            <p class="md-ui-hero__lede">Те же Markdown-файлы, что читают агенты: категории, аудитория и поиск по тексту.</p>
        </div>
    </section>

    <x-md.card title="Поиск">
        <form method="get" action="/module/knowledge_base" class="md-form md-form--inline">
            <x-md.input name="q" label="Название, описание, метка или текст" :value="$search" />
            <x-md.select name="category" label="Категория">
                <option value="">Все категории</option>
                @foreach ($categories as $key => $item)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $item['label'] }} ({{ $item['count'] }})</option>
                @endforeach
            </x-md.select>
            <x-md.button type="submit" variant="secondary" icon="search">Найти</x-md.button>
            <x-md.button href="/module/knowledge_base" variant="ghost">Сбросить</x-md.button>
        </form>
    </x-md.card>

    <x-md.card title="Материалы" :subtitle="'Найдено: ' . count($documents)">
        @if ($documents === [])
            <x-md.empty-state
                title="Материалов нет"
                text="Измените запрос или категорию. Новый файл docs/**/*.md появляется здесь сразу."
                icon="book"
            />
        @else
            <div class="md-table-wrap">
                <table class="md-table">
                    <thead>
                    <tr>
                        <th>Материал</th>
                        <th>Категория</th>
                        <th>Аудитория</th>
                        <th>Обновлён</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>
                                <a href="{{ $document['url'] }}"><strong>{{ $document['title'] }}</strong></a>
                                @if ($document['description'] !== '')
                                    <div class="md-table__muted">{{ $document['description'] }}</div>
                                @endif
                            </td>
                            <td><x-md.badge tone="neutral">{{ $document['category_label'] }}</x-md.badge></td>
                            <td>
                                @foreach ($document['audience'] as $audience)
                                    <x-md.badge :tone="$audience === 'user' ? 'info' : 'neutral'">
                                        {{ $audience === 'user' ? 'Пользователь' : 'Агент' }}
                                    </x-md.badge>
                                @endforeach
                            </td>
                            <td class="md-table__muted">{{ $document['updated_at'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-md.card>
</div>
