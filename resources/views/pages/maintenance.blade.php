<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Система"
        icon="tool"
        title="Обслуживание"
        lede="Флаг обслуживания отдаёт 503 для сайта и API. Админка, вход и health остаются доступны."
        :meta="[
            ['icon' => 'toggle-left', 'label' => 'Режим', 'value' => $enabled ? 'включён' : 'выключен'],
        ]">
        <x-slot:actions>
            <x-md.button href="/admin" variant="secondary" icon="arrow-left">На панель</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($enabled)
        <x-md.alert type="warning" title="Режим обслуживания включён"
                    text="Пользователи видят страницу обслуживания; вход администратора и health работают." />
    @else
        <x-md.alert type="success" title="Система работает в обычном режиме"
                    text="Сайт и API отвечают штатно." />
    @endif

    <x-md.card title="Переключить режим">
        <div class="md-ui-form-stack">
            <p class="md-ui-muted">
                Включайте режим на время обновления или миграции. Долгие операции лучше выполнять,
                когда сайт закрыт, чтобы пользователь не увидел половину данных.
            </p>
            <div class="md-ui-actions">
                @if (!$enabled)
                    <form method="post" action="/admin/maintenance/enable" class="md-inline-form"
                          data-md-confirm="Включить режим обслуживания? Сайт и API начнут отвечать 503.">
                        <input type="hidden" name="_token" value="{{ $csrf }}">
                        <x-md.button type="submit" variant="danger" icon="tool">Включить обслуживание</x-md.button>
                    </form>
                @else
                    <form method="post" action="/admin/maintenance/disable" class="md-inline-form">
                        <input type="hidden" name="_token" value="{{ $csrf }}">
                        <x-md.button type="submit" variant="primary" icon="check">Выключить обслуживание</x-md.button>
                    </form>
                @endif
            </div>
        </div>
    </x-md.card>

    <x-md.hint tone="info" title="Что остаётся доступным"
               :steps="[
                   'Вход администратора и страница /login.',
                   'Проверка /health и /api/v1/health.',
                   'Сама админка и режим обслуживания.',
                   'Сайт и остальные API отвечают 503.',
               ]" />
</div>
