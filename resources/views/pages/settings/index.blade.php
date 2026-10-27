<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="settings"
        title="Настройки"
        lede="Параметры установки с пояснениями. Изменения сохраняются в файл .env." />

    @yoyo('system-settings', ['group' => $group, 'search' => $search])
</div>
