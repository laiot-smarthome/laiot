<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="tool" /> core · ops</div>
            <h1 class="md-ui-hero__title">Обслуживание</h1>
            <p class="md-ui-hero__lede">Флаг `var/maintenance.flag`. Health и вход остаются доступны.</p>
        </div>
    </section>

    <x-md.card title="Статус">
        <p>
            @if ($enabled)
                <x-md.badge tone="warning">включено</x-md.badge>
            @else
                <x-md.badge tone="success">выключено</x-md.badge>
            @endif
        </p>
        <div class="md-ui-actions">
            <form method="post" action="/admin/maintenance/enable">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="danger" icon="tool">Включить</x-md.button>
            </form>
            <form method="post" action="/admin/maintenance/disable">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="secondary" icon="check">Выключить</x-md.button>
            </form>
        </div>
    </x-md.card>
</div>
