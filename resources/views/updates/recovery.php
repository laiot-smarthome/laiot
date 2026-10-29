<?php
// 2026-10-29 — автономное представление: не требует Blade, Composer или доступной БД.
$running = in_array($status, ['queued', 'running'], true);
$problem = in_array($status, ['interrupted', 'failed'], true);
$finished = in_array($status, ['succeeded', 'rolled_back'], true);
$pose = !$authorized ? 'quest' : ($problem ? 'error' : ($finished ? 'success' : 'work'));
$headline = !$authorized ? 'Восстановление системы' : ($problem ? 'Требуется восстановление' : ($finished ? 'Операция завершена' : 'Обновление системы'));
$plan = ['Проверка окружения', 'Подготовка релиза', 'Остановка записи и процессов', 'Снимок базы данных', 'Обновление данных', 'Переключение версии', 'Проверка запуска', 'Возобновление работы'];
?>
<!doctype html>
<html lang="ru" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
    <title>Обновление LaIOT</title>
    <?php if ($authorized && $running): ?><noscript><meta http-equiv="refresh" content="5"></noscript><?php endif ?>
    <link rel="stylesheet" href="/assets/admin/laiot.css"><script src="/assets/admin/laiot-updates.js" defer></script>
</head>
<body class="md-admin-shell md-update-recovery">
<main class="md-upd-console" <?= $authorized ? 'data-update-console' : '' ?> data-operation="<?= $escape($operation['id'] ?? '') ?>">
    <nav class="md-upd-topline" aria-label="Обновление системы"><span class="md-upd-wordmark">La<span>IOT</span><span class="md-upd-topline__divider"></span><small>Обновление системы</small></span><span class="md-upd-connection" data-update-connection><span></span> <?= $running ? 'На связи с сервером' : 'Центр восстановления' ?></span></nav>
    <header class="md-upd-hero md-upd-hero--console" data-update-tone="<?= $problem ? 'warning' : ($finished ? 'success' : 'working') ?>">
        <div class="md-upd-hero__copy"><span class="md-upd-kicker" data-update-status><?= $escape($authorized ? ($labels[$status] ?? $status) : 'Аварийный доступ') ?></span><h1 data-update-headline><?= $headline ?></h1><p data-update-message><?= $escape($authorized ? ($operation['message'] ?? 'Подготовка обновления LaIOT.') : 'Введите ключ восстановления для просмотра журнала и управления прерванной операцией.') ?></p>
            <?php if ($authorized): ?><div class="md-upd-versions"><span><?= $escape($operation['source']['id'] ?? '') ?></span><i class="ti ti-arrow-right" aria-hidden="true"></i><strong><?= $escape($operation['target']['id'] ?? '') ?></strong></div><?php endif ?>
        </div>
        <div class="md-upd-mascot-scene" aria-hidden="true"><div class="md-upd-orbit md-upd-orbit--one"></div><div class="md-upd-orbit md-upd-orbit--two"></div><span class="md-upd-spark md-upd-spark--one">+</span><span class="md-upd-spark md-upd-spark--two">+</span><img data-update-mascot src="/assets/img/maskot/<?= $pose ?>.png" alt="" width="250" height="250"><span class="md-upd-mascot-caption" data-update-caption><?= $problem ? 'Обновление остановлено' : ($finished ? 'Система готова к работе' : 'Операция выполняется на сервере') ?></span></div>
    </header>

    <?php if (!$authorized): ?>
        <section class="md-upd-unlock"><div><span class="md-upd-kicker">ДОСТУП ВЛАДЕЛЬЦА</span><h2>Ключ восстановления</h2><p>Введите ключ из <code>var/updates/recovery.key</code> на сервере. Основная панель и база для входа не нужны.</p></div><form method="post"><label for="update-recovery-key">Ключ восстановления</label><input class="md-input" id="update-recovery-key" type="password" name="recovery_key" required autocomplete="off"><button class="md-btn md-btn--primary" type="submit">Открыть восстановление <span aria-hidden="true">→</span></button></form></section>
    <?php else: ?>
        <div class="md-upd-console-grid">
            <section class="md-upd-progress-card"><div class="md-upd-section-head"><div><span class="md-upd-kicker">ХОД ОБНОВЛЕНИЯ</span><h2>Этапы выполнения</h2></div><span class="md-upd-step-count" data-update-counter>Этап <?= $progress['step'] ?> из 8</span></div><progress data-update-progress aria-label="Этапы переключения релиза" max="8" value="<?= $progress['step'] ?>"></progress>
                <ol class="md-upd-route" data-update-route><?php foreach ($plan as $index => $label): ?><li data-step="<?= $index + 1 ?>" data-state="<?= $index + 1 < $progress['step'] ? 'done' : ($index + 1 === $progress['step'] ? 'current' : 'pending') ?>"><span class="md-upd-route__number"><?= $index + 1 ?></span><span><?= $label ?></span><i class="ti ti-check" aria-hidden="true"></i></li><?php endforeach ?></ol>
                <div class="md-upd-snapshot"><i class="ti ti-database-heart" aria-hidden="true"></i><div><strong>Резервная копия базы данных</strong><span data-update-snapshot><?= isset($operation['snapshot_at']) ? $escape($operation['snapshot_at']) : 'Снимок будет сохранён перед изменением базы' ?></span></div></div>
                <details class="md-upd-journal"><summary>Журнал операции</summary><ol data-update-events><?php foreach ((array)($operation['events'] ?? []) as $event): ?><li><time><?= $escape($event['at']) ?></time><span><?= $escape($event['message']) ?></span></li><?php endforeach ?></ol></details>
            </section>
            <aside class="md-upd-waiting"><section class="md-upd-playroom" aria-label="Мини-игра во время ожидания"><?php require __DIR__ . '/snake.php'; ?></section><div class="md-upd-assurance"><i class="ti ti-shield-check" aria-hidden="true"></i><p>Операция выполняется на сервере.<br><strong>Открытая вкладка для работы процесса не требуется.</strong></p></div></aside>
        </div>
        <?php require __DIR__ . '/recovery-choice.php'; ?>
        <p role="alert" class="md-upd-warning" data-update-warning <?= empty($operation['warning']) ? 'hidden' : '' ?>><?= $escape($operation['warning'] ?? '') ?></p>
        <div class="md-upd-finished" data-update-finished <?= !$finished ? 'hidden' : '' ?>><i class="ti ti-circle-check" aria-hidden="true"></i><div><h2>Система готова к работе</h2><p>Операция завершена. Доступ к системе восстановлен.</p></div><a class="md-btn md-btn--primary" href="/admin/updates">Вернуться в панель <span aria-hidden="true">→</span></a></div>
    <?php endif ?>
    <footer class="md-upd-console-footer"><span>LaIOT · управление обновлениями</span><span>Эта страница работает независимо от основной панели</span></footer>
</main>
</body></html>
