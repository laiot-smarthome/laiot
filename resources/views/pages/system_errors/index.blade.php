<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="alert-triangle"
        title="Ошибки"
        lede="Полный разбор записи: когда и где произошла ошибка, класс исключения, стек, контекст и исходный файл — в одной панели справа." />

    @if ($done !== '')
        <x-md.alert type="success" :text="$done" />
    @endif

    @yoyo('system-errors', [
        'search' => $search,
        'level' => $level,
        'flat' => $flat,
        'message' => $message,
    ])
</div>
