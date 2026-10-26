<link rel="stylesheet" href="/assets/modules/shoutbox/shoutbox.css?v=2026-10-22-2">

<div class="md-ui-page md-ui-page--stack md-shoutbox-page">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="message" /> Система · say()</div>
            <h1 class="md-ui-hero__title">{{ defined('LANG_MODULE_SHOUTBOX') ? constant('LANG_MODULE_SHOUTBOX') : 'Shoutbox' }}</h1>
            <p class="md-ui-hero__lede">
                Лента того, что уходит в чат через <code>say()</code>: системные события,
                сообщения сценариев, методов объектов и пользователей.
            </p>
        </div>
    </section>

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Всего сообщений" :value="$TOTAL_SHOUTS ?? 0" icon="message" tone="info" meta="За всё время" />
        <x-md.kpi label="В журнале" :value="$SHOWN_SHOUTS ?? 0" icon="list-details" tone="neutral" meta="Показаны последние" />
        <x-md.kpi label="Системных" :value="$SYSTEM_SHOUTS ?? 0" icon="terminal-2" tone="success" meta="Вызовы say()" />
        <x-md.kpi label="Последнее" :value="$LAST_SHOUT_TIME ?? '—'" icon="clock" tone="warning" :meta="$LAST_SHOUT_DATE ?? 'Нет сообщений'" />
    </div>

    <div class="md-shoutbox-layout">
        <div class="md-shoutbox-main">
            {!! $SHOUT_FEED ?? '' !!}
        </div>
        <aside class="md-shoutbox-side" aria-label="Подсказки по say()">
            {!! $SHOUT_HINT ?? '' !!}
            {!! $SHOUT_CODE ?? '' !!}
        </aside>
    </div>
</div>
