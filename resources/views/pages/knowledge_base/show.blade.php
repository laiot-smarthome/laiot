<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        :eyebrow="$document['category_label']"
        icon="book"
        :title="$document['title']"
        :lede="$document['description'] !== '' ? $document['description'] : null"
        :meta="[['icon' => 'clock', 'label' => 'Обновлено', 'value' => (new \DateTimeImmutable($document['updated_at']))->format('d.m.Y H:i')]]">
        <x-slot:actions>
            <x-md.button :href="$backUrl" variant="secondary" icon="arrow-left">К результатам поиска</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <div class="md-ui-actions">
        @foreach ($document['audience'] as $audience)
            <x-md.badge :tone="$audience === 'user' ? 'info' : 'neutral'">
                {{ $audience === 'user' ? 'Пользователю' : 'Агенту' }}
            </x-md.badge>
        @endforeach
        @foreach ($document['tags'] as $tag)
            <x-md.badge tone="outline">{{ $tag }}</x-md.badge>
        @endforeach
    </div>

    <div @class(['md-kb__reader', 'md-kb__reader--with-toc' => $document['toc'] !== []])>
        @if ($document['toc'] !== [])
            <aside class="md-kb__toc">
                <x-md.card compact>
                    <details class="md-kb__contents" open>
                        <summary>Содержание</summary>
                        <nav aria-label="Содержание статьи">
                            <ul>
                                @foreach ($document['toc'] as $item)
                                    <li @class(['md-kb__toc-child' => $item['level'] === 3])>
                                        <a href="#{{ $item['id'] }}">{{ $item['title'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    </details>
                </x-md.card>
            </aside>
        @endif
        <x-md.card class="md-kb__article">
            <article class="md-prose" aria-label="{{ $document['title'] }}"
                     x-data="{ async copy(event) {
                         const button = event.target.closest('[data-kb-copy]');
                         if (!button) return;
                         const code = button.closest('.md-code-sample').querySelector('pre code');
                         try {
                             await navigator.clipboard.writeText(code.textContent.replace(/\n$/, ''));
                             button.textContent = 'Скопировано';
                         } catch {
                             button.textContent = 'Не удалось скопировать';
                         }
                     } }" @click="copy($event)">
                {!! $document['html'] !!}
            </article>
        </x-md.card>
    </div>
</div>
