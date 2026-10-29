<?php // 2026-10-29 — выбор восстановления отделён от игровой комнаты и требует явного согласия. ?>
<section class="md-upd-recovery-choice" data-update-recovery <?= !$problem ? 'hidden' : '' ?>>
    <div class="md-upd-section-head"><div><span class="md-upd-kicker">ДЕЙСТВИЯ ВОССТАНОВЛЕНИЯ</span><h2>Восстановление после прерывания</h2></div></div>
    <p data-update-interrupted><?= $status === 'interrupted' ? 'Процесс больше не работает. Сервер мог перезагрузиться или завершить службу. Последний завершённый снимок сохранён.' : 'Операция остановлена. Изучите ошибку и выберите повтор или восстановление предыдущей версии.' ?></p>
    <p class="md-upd-error" role="alert" data-update-error <?= ($error ?: ($operation['error'] ?? '')) === '' ? 'hidden' : '' ?>><?= $escape($error ?: ($operation['error'] ?? '')) ?></p>
    <div class="md-upd-choice-grid"><div><span>01</span><h3>Попробовать ещё раз</h3><p>Вернём исходные код и БД, затем повторим обновление с начала.</p></div><div><span>02</span><h3>Вернуть прежнюю версию</h3><p>Будут восстановлены прежний релиз и состояние БД на момент сохранённого снимка.</p></div></div>
    <form method="post"><input type="hidden" name="csrf" value="<?= $escape(hash_hmac('sha256', 'recover', $key)) ?>"><input type="hidden" name="operation" value="<?= $escape($operation['id']) ?>">
        <label class="md-upd-consent"><input type="checkbox" data-md-raw-checkbox name="confirmed" value="1" required><span>Понимаю: более новые код сценариев, привязки, настройки и другие данные MySQL могут быть потеряны. Файлы var и сторонние модули сохранятся.</span></label>
        <div class="md-upd-actions"><button class="md-btn md-btn--primary" name="action" value="retry">Попробовать ещё раз <span aria-hidden="true">↗</span></button><button class="md-btn md-btn--secondary" name="action" value="rollback">Вернуть прежнюю версию</button></div>
    </form>
</section>
