<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="bell" /> core · notifications</div>
            <h1 class="md-ui-hero__title">Оповещения</h1>
            <p class="md-ui-hero__lede">Непрочитанные системные уведомления. Прочтение — только явной кнопкой.</p>
        </div>
    </section>

    @if (($notifications['items'] ?? []) === [])
        <x-md.empty-state title="Оповещений нет" text="Все уведомления прочитаны." icon="bell" />
    @else
        <x-md.card title="Непрочитанные">
            <div class="md-ui-stack">
                @foreach ($notifications['items'] as $note)
                    <article class="md-alert md-alert--{{ in_array($note['type'], ['info', 'success', 'warning', 'danger'], true) ? $note['type'] : 'info' }}">
                        <div class="md-alert__body">
                            <div class="md-notification__meta">
                                <span class="md-badge md-badge--outline">{{ $note['module_title'] }}</span>
                                <time class="small">{{ $note['added'] }}</time>
                            </div>
                            @if ($note['title'] !== '')
                                <div class="md-alert__title">{{ $note['title'] }}</div>
                            @endif
                            <div class="md-alert__text">{{ $note['message'] }}</div>
                            <div class="md-alert__actions">
                                @if ($note['url'] !== '')
                                    <x-md.button :href="$note['url']" variant="secondary" size="sm">{{ $note['label'] !== '' ? $note['label'] : 'Подробнее' }}</x-md.button>
                                @endif
                                <form method="post" action="/notifications/{{ $note['id'] }}/dismiss">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.button type="submit" variant="ghost" size="sm" icon="check">Прочитано</x-md.button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-md.card>
    @endif
</div>
