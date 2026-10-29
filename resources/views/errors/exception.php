<?php

declare(strict_types=1);

// 2026-10-29 — аварийный шаблон на обычном PHP: работает без Blade и контейнера.
$source = static function (array $frame) use ($escape): void {
    if ($frame['source'] === []) {
        echo '<p class="md-exception__unavailable">Фрагмент исходника недоступен.</p>';

        return;
    }
    ?>
    <div class="md-exception__source" tabindex="0" role="region" aria-label="Фрагмент исходника">
        <pre><?php foreach ($frame['source'] as $number => $code) : ?><span class="md-exception__line<?= $number === $frame['line'] ? ' is-error' : '' ?>"><span class="md-exception__line-number" aria-hidden="true"><?= $number ?></span><code><?= $escape($code) ?></code></span><?php endforeach ?></pre>
    </div>
    <?php
};
$primary = $exceptions[0] ?? null;
?>
<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>500 — <?= $debug ? 'Ошибка приложения' : 'Внутренняя ошибка' ?> — LaIoT</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/admin/mydomo.css<?= $escape($assetVersion) ?>">
    <script>
        (function () {
            var match = document.cookie.match(/(?:^|;\s*)theme=([^;]+)/);
            document.documentElement.setAttribute('data-bs-theme', match && match[1] === 'dark' ? 'dark' : 'light');
        })();
    </script>
</head>
<body class="md-admin-shell <?= $debug ? 'md-exception' : 'md-error-page' ?>" data-error-code="server_error" data-error-status="500">
<?php if ($primary !== null) : ?>
<main class="md-exception__main" x-data="mdException">
    <header class="md-exception__toolbar">
        <a class="md-exception__brand md-logo" href="/admin" aria-label="LaIoT — в панель">
            <img class="md-logo__light" src="/assets/img/logos/laiot-logo.png" srcset="/assets/img/logos/laiot-logo@2x.png 2x" width="333" height="101" alt="LaIoT">
            <img class="md-logo__dark" src="/assets/img/logos/laiot-logo-white.png" srcset="/assets/img/logos/laiot-logo-white@2x.png 2x" width="300" height="91" alt="LaIoT">
        </a>
        <div class="md-exception__toolbar-actions">
            <span class="md-exception__badge">500 · Режим отладки</span>
            <button type="button" class="md-btn md-btn--secondary" disabled :disabled="copying" @click="copy()">
                <i class="ti ti-copy" aria-hidden="true"></i><span x-text="copying ? 'Копирование…' : 'Скопировать для ИИ'">Скопировать для ИИ</span>
            </button>
        </div>
    </header>
    <section class="md-exception__card md-exception__summary" aria-labelledby="exception-title">
        <p class="md-exception__type"><?= $escape($primary['class']) ?></p>
        <h1 id="exception-title"><?= $escape($primary['message'] !== '' ? $primary['message'] : 'Исключение без сообщения') ?></h1>
        <p class="md-exception__location"><?= $escape($primary['frames'][0]['file']) ?><strong>:<?= $primary['frames'][0]['line'] ?></strong></p>
        <?php $source($primary['frames'][0]); ?>
    </section>

    <div class="md-exception__layout">
        <section class="md-exception__card md-exception__trace" aria-labelledby="trace-title">
            <div class="md-exception__section-heading">
                <h2 id="trace-title">Трассировка</h2>
                <span>Кадров: <?= count($primary['frames']) ?><?= $primary['truncated'] ? ' · сокращено' : '' ?></span>
            </div>
            <p class="md-exception__hint">Раскройте вызов, чтобы увидеть код в этом месте.</p>
            <?php foreach ($primary['frames'] as $index => $frame) : ?>
            <details class="md-exception__frame<?= $frame['vendor'] ? ' md-exception__frame--vendor' : '' ?>">
                <summary>
                    <span class="md-exception__frame-index"><?= $index ?></span>
                    <span class="md-exception__frame-info"><strong><?= $escape($frame['call']) ?></strong><span><?= $escape($frame['file'] !== '' ? $frame['file'] : '[внутренний вызов PHP]') ?><?= $frame['line'] > 0 ? ':' . $frame['line'] : '' ?></span></span>
                    <?php if ($frame['vendor']) : ?><span class="md-exception__vendor-label">vendor</span><?php endif ?>
                </summary>
                <?php $source($frame); ?>
            </details>
            <?php endforeach ?>
        </section>

        <aside class="md-exception__sidebar">
            <section class="md-exception__card md-exception__request" aria-labelledby="request-title">
                <h2 id="request-title">Запрос</h2>
                <dl>
                    <dt>Метод и путь</dt><dd><code><?= $escape($method) ?> <?= $escape($path) ?></code></dd>
                    <dt>Код запроса</dt><dd><code><?= $escape($requestId) ?></code></dd>
                    <dt>PHP</dt><dd><?= $escape(PHP_VERSION) ?></dd>
                </dl>
            </section>
            <section class="md-exception__card md-exception__notice">
                <h2>Отладка включена</h2>
                <p>Технические подробности видны при <code>APP_DEBUG=true</code>. При выключенной отладке показывается обычная страница ошибки.</p>
                <a class="md-btn md-btn--secondary" href="/admin">В панель</a>
            </section>
        </aside>
    </div>

    <?php if (count($exceptions) > 1) : ?>
    <section class="md-exception__card md-exception__causes" aria-labelledby="causes-title">
        <h2 id="causes-title">Предыдущие исключения</h2>
        <?php foreach (array_slice($exceptions, 1) as $cause) : ?>
        <details class="md-exception__cause">
            <summary><strong><?= $escape($cause['class']) ?></strong><span><?= $escape($cause['message']) ?></span></summary>
            <?php foreach ($cause['frames'] as $frame) : ?>
            <details class="md-exception__frame">
                <summary><span class="md-exception__frame-info"><strong><?= $escape($frame['call']) ?></strong><span><?= $escape($frame['file']) ?>:<?= $frame['line'] ?></span></span></summary>
                <?php $source($frame); ?>
            </details>
            <?php endforeach ?>
        </details>
        <?php endforeach ?>
    </section>
    <?php endif ?>
    <details class="md-exception__card md-exception__report" :open="manual">
        <summary>Текст ошибки для ИИ</summary>
        <textarea x-ref="report" readonly rows="16" aria-label="Отчёт об ошибке для ИИ"><?= $escape($report) ?></textarea>
    </details>
</main>
<?php require __DIR__ . '/../components/md/toast-stack.php'; ?>
<script src="/assets/admin/mydomo-exceptions.js<?= $escape($scriptVersion) ?>" defer></script>
<?php else : ?>
<main class="md-error-page__main">
    <div class="md-error-page__card md-error-page__card--danger">
        <a class="md-error-page__brand" href="/admin" aria-label="LaIoT — в панель">
            <span class="md-logo">
                <img class="md-logo__light" src="/assets/img/logos/laiot-logo.png" width="333" height="101" alt="LaIoT">
                <img class="md-logo__dark" src="/assets/img/logos/laiot-logo-white.png" width="300" height="91" alt="LaIoT">
            </span>
        </a>
        <div class="md-error-page__scene">
            <img class="md-mascot" src="/assets/img/maskot/error.png" width="160" height="160" alt="">
            <span class="md-error-page__code">500</span>
        </div>
        <h1 class="md-error-page__title">Внутренняя ошибка</h1>
        <p class="md-error-page__text">Сервер не смог обработать запрос. Повторите попытку или вернитесь в панель.</p>
        <p class="md-error-page__path"><?= $escape($path) ?></p>
        <p class="md-error-page__request">Код запроса: <code><?= $escape($requestId) ?></code></p>
        <div class="md-error-page__actions"><a class="md-btn md-btn--primary" href="/admin">В панель</a></div>
    </div>
</main>
<?php endif ?>
</body>
</html>
