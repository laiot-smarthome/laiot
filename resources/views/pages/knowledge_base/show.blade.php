<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow">
                <a href="/module/knowledge_base"><x-md.icon name="arrow-left" /> База знаний</a>
                · {{ $document['category_label'] }}
            </div>
            <h1 class="md-ui-hero__title">{{ $document['title'] }}</h1>
            @if ($document['description'] !== '')
                <p class="md-ui-hero__lede">{{ $document['description'] }}</p>
            @endif
            <div class="md-ui-actions">
                @foreach ($document['audience'] as $audience)
                    <x-md.badge :tone="$audience === 'user' ? 'info' : 'neutral'">
                        {{ $audience === 'user' ? 'Пользователь' : 'Агент' }}
                    </x-md.badge>
                @endforeach
                <x-md.badge tone="neutral">Обновлён {{ $document['updated_at'] }}</x-md.badge>
                <x-md.badge tone="neutral"><code>{{ $document['id'] }}</code></x-md.badge>
            </div>
        </div>
    </section>

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
                            <li class="{{ $item['level'] === 3 ? 'ms-3' : '' }}">
                                <a href="#{{ $item['id'] }}">{{ $item['title'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </x-md.card>
            </div>
        @endif
    </div>
</div>
