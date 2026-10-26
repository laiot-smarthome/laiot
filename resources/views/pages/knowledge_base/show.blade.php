<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$document['category_label']"
        icon="book"
        :title="$document['title']"
        :lede="$document['description'] !== '' ? $document['description'] : null"
        :meta="[
            ['icon' => 'clock', 'label' => 'Обновлён', 'value' => $document['updated_at']],
            ['icon' => 'file-text', 'label' => 'Файл', 'value' => $document['id'] . '.md'],
        ]">
        <x-slot:actions>
            <x-md.button href="/knowledge-base" variant="secondary" icon="arrow-left">К списку</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <div class="md-ui-actions">
        @foreach ($document['audience'] as $audience)
            <x-md.badge :tone="$audience === 'user' ? 'info' : 'neutral'">
                {{ $audience === 'user' ? 'Пользователю' : 'Агенту' }}
            </x-md.badge>
        @endforeach
        @foreach ($document['tags'] as $tag)
            <x-md.badge tone="outline">#{{ $tag }}</x-md.badge>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-9">
            <x-md.card>
                <article class="md-prose">
                    {!! $document['html'] !!}
                </article>
            </x-md.card>
        </div>
        @if ($document['toc'] !== [])
            <div class="col-lg-3">
                <x-md.card title="Содержание">
                    <ul class="list-unstyled mb-0">
                        @foreach ($document['toc'] as $item)
                            <li class="{{ $item['level'] === 3 ? 'ms-3' : '' }} mb-1">
                                <a href="#{{ $item['id'] }}">{{ $item['title'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </x-md.card>
            </div>
        @endif
    </div>
</div>
