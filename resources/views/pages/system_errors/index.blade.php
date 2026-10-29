<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="alert-triangle"
        title="Ошибки"
        lede="Журнал ошибок ядра: группы повторов, поиск и фильтры. Откройте сообщение, чтобы увидеть место сбоя, стек и контекст." />


    @yoyo('system-errors', [
        'search' => $search,
        'level' => $level,
        'flat' => $flat,
        'message' => $message,
    ])
</div>
