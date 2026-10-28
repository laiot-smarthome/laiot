<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero eyebrow="Автоматизация" icon="route" title="События"
                    lede="Цепочки автоматизации: что запустило обработку, какие действия последовали и где возникла ошибка." />

    @yoyo('events-table', [
        'search' => $search,
        'type' => $type,
        'status' => $status,
        'selectedId' => $selectedId,
        'page' => $page,
    ])
</div>
