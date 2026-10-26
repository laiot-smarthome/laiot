@php
    $items = $notifications['items'] ?? [];
    $tones = ['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'];
@endphp

<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="bell"
        title="Оповещения"
        lede="Непрочитанные уведомления модулей и ядра. Просмотр не отмечает их прочитанными — для этого есть отдельная кнопка."
        :meta="[
            ['icon' => 'bell', 'label' => 'Непрочитанных', 'value' => count($items)],
        ]" />

    @if ($items === [])
        <x-md.empty-state icon="bell" title="Непрочитанных оповещений нет"
                          text="Все уведомления прочитаны. Новые появятся здесь и в колокольчике шапки." />
    @else
        <div class="md-ui-page--stack">
            @foreach ($items as $note)
                <article class="md-alert md-alert--{{ $tones[$note['type']] ?? 'info' }}">
                    <span class="md-alert__icon"><i class="ti ti-bell" aria-hidden="true"></i></span>
                    <div class="md-alert__body">
                        <div class="md-ui-actions">
                            <x-md.badge tone="outline">{{ $note['module_title'] }}</x-md.badge>
                            <span class="md-table__muted">{{ $note['added'] }}</span>
                        </div>
                        @if ($note['title'] !== '')
                            <div class="md-alert__title">{{ $note['title'] }}</div>
                        @endif
                        <div class="md-alert__text">{{ $note['message'] }}</div>
                        <div class="md-alert__actions">
                            @if ($note['url'] !== '')
                                <x-md.button :href="$note['url']" variant="secondary" size="sm" icon="arrow-right">
                                    {{ $note['label'] !== '' ? $note['label'] : 'Подробнее' }}
                                </x-md.button>
                            @endif
                            <form method="post" action="/notifications/{{ $note['id'] }}/dismiss" class="md-inline-form">
                                <input type="hidden" name="_token" value="{{ $csrf }}">
                                <x-md.button type="submit" variant="ghost" size="sm" icon="check">Прочитано</x-md.button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
