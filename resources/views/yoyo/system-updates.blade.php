{{-- 2026-10-30 — немедленный settle сохраняет Alpine-стили диалога при повторной проверке. --}}
<div class="md-upd-admin" hx-swap="outerHTML settle:0ms" x-data="{busy: '', activeRelease: {id: '', html: '', url: ''}}"
     x-on:htmx:before-request.camel="busy = $event.detail.elt.closest('[data-update-action]')?.dataset.updateAction || 'request'"
     x-on:htmx:after-request.camel="busy = ''; if ($event.detail.failed) $store.mdToasts.push({tone: 'danger', text: $event.detail.xhr?.status === 403 ? 'Доступ к запросу отклонён. Обновите страницу и проверьте авторизацию.' : 'Не удалось выполнить запрос. Проверьте соединение и повторите попытку.'})">
<header class="md-upd-hero">
    <div class="md-upd-hero__copy">
        <span class="md-upd-kicker"><x-md.icon name="sparkles" /> ОБНОВЛЕНИЕ СИСТЕМЫ</span>
        <h1>Обновление<br><span>системы.</span></h1>
        <p>Выберите релиз, проверьте изменения<br>и запустите обновление LaIOT.</p>
        <div class="md-upd-hero__bottom"><span class="md-upd-current"><span></span> Сейчас {{ $current['id'] }}</span><span class="md-upd-source">{{ $repository }}</span></div>
    </div>
    <div class="md-upd-mascot-scene" aria-hidden="true"><div class="md-upd-orbit md-upd-orbit--one"></div><div class="md-upd-orbit md-upd-orbit--two"></div><span class="md-upd-spark md-upd-spark--one">+</span><span class="md-upd-spark md-upd-spark--two">+</span><x-md.mascot pose="work" /><span class="md-upd-mascot-caption">Проверка и установка релизов</span></div>
</header>
<div class="md-upd-safety"><span><x-md.icon name="puzzle" /> Сторонние модули сохранятся</span><span><x-md.icon name="database-heart" /> Снимок перед изменениями</span><span><x-md.icon name="hand-click" /> Ручной запуск обновления</span></div>

@if (in_array($operation['status'] ?? '', ['queued', 'running', 'failed', 'interrupted'], true))
    <section class="md-upd-resume"><div class="md-upd-resume__icon"><x-md.icon name="route" /></div><div><h2>Обнаружена незавершённая операция</h2><p>Откройте журнал, чтобы повторить операцию или восстановить предыдущую версию.</p></div><x-md.button href="/admin/updates/recovery" iconAfter="arrow-up-right">Продолжить</x-md.button></section>
@endif

<section class="md-upd-release-board" x-bind:aria-busy="busy !== ''">
    <div class="md-upd-section-head"><div><span class="md-upd-kicker">ДОСТУПНЫЕ ВЕРСИИ</span><h2>Релизы системы</h2><p>Опубликованные выпуски выбранного канала и сохранённые версии.</p></div></div>
    <div class="md-upd-release-toolbar">
        <div class="md-upd-channel-tabs" role="group" aria-label="Канал обновлений">
            <button type="button" yoyo:get="changeChannel('main')" data-update-action="changeChannel" x-bind:disabled="busy !== ''" aria-pressed="{{ $channel === 'main' ? 'true' : 'false' }}"><span class="md-upd-channel-icon"><x-md.icon name="shield-check" /></span><span class="md-upd-channel-copy"><strong>Стабильный</strong><small>main</small></span><x-md.icon name="circle-check" class="md-upd-channel-check" /></button>
            <button type="button" yoyo:get="changeChannel('beta')" data-update-action="changeChannel" x-bind:disabled="busy !== ''" aria-pressed="{{ $channel === 'beta' ? 'true' : 'false' }}"><span class="md-upd-channel-icon"><x-md.icon name="flask" /></span><span class="md-upd-channel-copy"><strong>Тестовый</strong><small>beta</small></span><x-md.icon name="circle-check" class="md-upd-channel-check" /></button>
        </div>
        <x-md.button variant="secondary" yoyo:post="checkUpdates" data-update-action="refresh" x-bind:disabled="busy !== ''" class="md-upd-refresh"><span x-show="busy !== 'refresh'"><span class="md-upd-button-content"><x-md.icon name="refresh" /> Проверить обновления</span></span><span x-show="busy === 'refresh'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Проверка обновлений…</span></span></x-md.button>
    </div>
    <div class="md-upd-loading" role="status" x-show="busy === 'refresh'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Получение списка релизов…</span></div>
    <div class="md-upd-loading" role="status" x-show="busy === 'changeChannel'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Загрузка выбранного канала…</span></div>
    <div class="md-upd-loading" role="status" x-show="busy === 'inspect'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Проверка релиза и совместимости модулей…</span></div>
    <div class="md-upd-release-results" x-bind:class="{'is-loading': busy !== ''}">
    @if ($releases === [])
        <div class="md-upd-no-releases"><span class="md-upd-no-releases__icon"><x-md.icon name="planet" /></span><h3>Нет доступных релизов</h3><p>Обновите список выпусков или выберите другой канал.</p></div>
    @else
        <div class="md-upd-release-carousel" x-data="mdReleaseSlider" x-effect="setEnabled(busy === '')" x-on:htmx:after-settle.camel.window="if ($event.detail.elt?.contains($el)) $nextTick(() => mount())">
        <div class="md-upd-carousel-toolbar"><span class="md-upd-release-count">Доступно: {{ count($releases) - 1 }}</span><div class="md-upd-carousel-navigation"><button type="button" class="md-upd-current-jump" x-on:click="goTo(@js($current['id']))" x-bind:disabled="busy !== '' || activeId === @js($current['id'])"><x-md.icon name="circle-dot" /> Моя версия</button><button type="button" class="md-upd-carousel-arrow" x-on:click="previous()" x-bind:disabled="busy !== '' || atStart" aria-label="Предыдущая карточка"><x-md.icon name="chevron-left" /></button><span class="md-upd-carousel-counter" aria-live="polite" x-text="index + ' / ' + total">1 / {{ count($releases) }}</span><button type="button" class="md-upd-carousel-arrow" x-on:click="next()" x-bind:disabled="busy !== '' || atEnd" aria-label="Следующая карточка"><x-md.icon name="chevron-right" /></button></div></div>
        <div class="swiper md-upd-release-viewport" x-ref="viewport" tabindex="0" aria-label="Версии системы" x-on:keydown.arrow-left.prevent="previous()" x-on:keydown.arrow-right.prevent="next()">
        <div class="swiper-wrapper">
            @foreach ($releases as $release)
                <article class="swiper-slide md-upd-release-card" data-release="{{ $release['id'] }}" data-current="{{ $release['current'] ? 'true' : 'false' }}" data-selected="{{ ($preview['manifest']['id'] ?? '') === $release['id'] ? 'true' : 'false' }}" aria-labelledby="release-{{ $release['id'] }}">
                    <div class="md-upd-release-card__head">
                        <span class="md-upd-release-card__icon"><x-md.icon name="package" /></span>
                        <div class="md-upd-release-card__title"><h3 id="release-{{ $release['id'] }}" title="{{ $release['title'] ?? $release['id'] }}">{{ $release['id'] }}</h3><div class="md-upd-release-card__meta"><span><x-md.icon name="calendar" /> {{ isset($release['published_at']) && strtotime($release['published_at']) !== false ? date('d.m.Y', strtotime($release['published_at'])) : ($release['current'] ? 'Установлена' : 'Сохранён локально') }}</span>@if (!empty($release['size']))<span><x-md.icon name="download" /> {{ number_format($release['size'] / 1048576, 1, ',', ' ') }} МБ</span>@endif</div></div>
                    </div>
                    <div class="md-upd-release-card__summary"><div class="md-upd-release-card__summary-head"><h4>Список изменений</h4>@if ($release['current'])<x-md.badge tone="primary" dot>Текущая версия</x-md.badge>@elseif ($release['new'])<x-md.badge tone="success" dot>Новая версия</x-md.badge>@endif</div>@if ($release['summary'] !== '')<div class="md-prose">{!! $release['summary'] !!}</div>@else<p>Описание изменений не опубликовано.</p>@endif</div>
                    <div class="md-upd-release-card__actions">
                        <button type="button" class="md-upd-release-card__more" x-on:click="activeRelease = @js(['id' => $release['id'], 'html' => $release['notes_html'], 'url' => $release['url']]); $store.mdModal.show('update-release-notes')" x-bind:disabled="busy !== ''">Читать полностью <x-md.icon name="arrows-maximize" /></button>
                        @if ($release['url'] !== '')<a href="{{ $release['url'] }}" target="_blank" rel="noopener noreferrer" class="md-upd-release-card__github"><x-md.icon name="brand-github" /> Релиз на GitHub <x-md.icon name="external-link" /></a>@endif
                        @unless ($release['current'])<x-md.button id="prepare-{{ hash('sha256', $release['id']) }}" variant="secondary" yoyo:get="inspect('{{ $release['id'] }}')" data-update-action="inspect" x-bind:disabled="busy !== ''" iconAfter="arrow-right">Подготовить установку</x-md.button>@endunless
                    </div>
                </article>
            @endforeach
        </div>
        </div>
        </div>
    @endif
    </div>
</section>

<x-md.modal id="update-release-notes" title="Изменения в релизе" size="xl" class="md-upd-notes-modal">
    <p class="md-upd-notes-version" x-text="activeRelease.id"></p>
    <div class="md-prose" x-html="activeRelease.html" x-show="activeRelease.html !== ''"></div>
    <p x-show="activeRelease.html === ''">Описание изменений не опубликовано.</p>
    <x-slot:actions>
        <x-md.button variant="secondary" x-on:click="$store.mdModal.close()">Закрыть</x-md.button>
        <x-md.button href="#" x-bind:href="activeRelease.url" x-show="activeRelease.url !== ''" target="_blank" rel="noopener noreferrer" icon="brand-github" iconAfter="external-link">Открыть на GitHub</x-md.button>
    </x-slot:actions>
</x-md.modal>

@if ($installRequested)
<div x-data="{confirmed: false}" x-init="$nextTick(() => { document.getElementById(@js('prepare-' . hash('sha256', $selected)))?.focus(); $store.mdModal.show('update-release-install') })">
    <x-md.modal id="update-release-install" title="Подготовка установки" size="lg" class="md-upd-install-modal">
        @if ($preview !== [])
            <div class="md-upd-install-version"><span>{{ $current['id'] }}</span><x-md.icon name="arrow-right" /><strong>{{ $preview['manifest']['id'] }}</strong></div>
            <section class="md-upd-install-status" data-ready="{{ $canInstall ? 'true' : 'false' }}" role="status">
                <div class="md-upd-install-status__copy"><span class="md-upd-install-status__label">ПРОВЕРКА РЕЛИЗА</span><h3>{{ $canInstall ? 'Установка доступна' : 'Установка недоступна' }}</h3><p>{{ $operationBusy ? 'Сначала завершите или восстановите предыдущую операцию.' : ($canInstall ? 'Проверки пройдены. Подтвердите последствия, чтобы продолжить.' : 'Для установки необходимо устранить обнаруженные ошибки.') }}</p>@if ($operationBusy)<x-md.button href="/admin/updates/recovery" variant="secondary" size="sm">Открыть восстановление</x-md.button>@else<span class="md-upd-install-status__count">Пройдено проверок: {{ $checksPassed }} из {{ count($checks) }}</span>@endif</div>
                <x-md.mascot :pose="$canInstall ? 'success' : 'error'" />
            </section>
            <ul class="md-upd-install-checks" aria-label="Результаты проверок">
                @foreach ($checks as $check)
                    <li data-ok="{{ $check['ok'] ? 'true' : 'false' }}"><x-md.spoiler :title="$check['title']" :icon="$check['ok'] ? 'circle-check' : 'circle-x'" :aria-label="$check['title'] . ': ' . ($check['ok'] ? 'проверка пройдена' : 'ошибка')" variant="flush"><p>{{ $check['detail'] }}</p></x-md.spoiler></li>
                @endforeach
            </ul>
            @if ($notes !== '')<x-md.spoiler title="Изменения в релизе" icon="list-details" class="md-upd-install-notes"><div class="md-prose">{!! $notes !!}</div></x-md.spoiler>@endif
            <form id="update-install-form" yoyo:post="start" data-update-action="start"><fieldset class="md-upd-install-consent" x-bind:disabled="busy !== ''">
                <input type="hidden" name="release" value="{{ $preview['manifest']['id'] }}">
                <div class="md-upd-install-consequences"><strong>Система будет временно приостановлена</strong><p>Перед изменениями остановятся запись и фоновые процессы. Установка начнётся после сохранения снимка базы данных.</p></div>
                @if ($preview['snapshot'] !== [])<div class="md-upd-data-warning"><x-md.icon name="history" /><div><strong>База данных будет восстановлена на {{ $preview['snapshot']['created_at'] }}</strong><p>Более новые пользовательский код, сценарии, привязки и настройки MySQL будут потеряны. Сторонние модули и файлы var сохранятся.</p></div></div>@endif
                <x-md.checkbox name="confirmed" x-model="confirmed" :disabled="!$canInstall" label="Понимаю последствия и разрешаю переключение версии" required />
                @if (!$canInstall)<p class="md-upd-install-hint">Подтверждение станет доступно после устранения причин блокировки.</p>@endif
            </fieldset></form>
        @endif
        @if ($error !== '')<p class="md-upd-install-error" role="alert">{{ $error }}</p>@endif
        <x-slot:actions>
            <x-md.button variant="secondary" x-on:click="$store.mdModal.close()" x-bind:disabled="busy !== ''">Закрыть</x-md.button>
            @if ($preview !== [])
                <x-md.button variant="secondary" yoyo:get="inspect('{{ $preview['manifest']['id'] }}')" data-update-action="inspect" x-bind:disabled="busy !== ''"><span x-show="busy !== 'inspect'">Повторить проверку</span><span x-show="busy === 'inspect'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Проверка…</span></span></x-md.button>
                <x-md.button type="submit" form="update-install-form" :disabled="!$canInstall" x-bind:disabled="busy !== '' || !confirmed || {{ $canInstall ? 'false' : 'true' }}"><span x-show="busy !== 'start'">{{ $preview['snapshot'] !== [] ? 'Восстановить версию' : 'Установить релиз' }}</span><span x-show="busy === 'start'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Подготовка обновления…</span></span></x-md.button>
            @endif
        </x-slot:actions>
    </x-md.modal>
</div>
@endif

<section class="md-upd-how"><div class="md-upd-section-head"><div><span class="md-upd-kicker">ПОРЯДОК ВЫПОЛНЕНИЯ</span><h2>Как выполняется обновление</h2></div></div><div class="md-upd-how-grid">
    <div><span>01</span><x-md.icon name="scan" /><h3>Проверка окружения</h3><p>Проверка PHP, доступа к файлам и совместимости модулей.</p></div>
    <div><span>02</span><x-md.icon name="database-heart" /><h3>Резервная копия</h3><p>Создание последнего снимка MySQL текущего релиза.</p></div>
    <div><span>03</span><x-md.icon name="rocket" /><h3>Установка релиза</h3><p>Обновление ядра с сохранением файлов и сторонних модулей.</p></div>
    <div><span>04</span><x-md.icon name="home-check" /><h3>Проверка запуска</h3><p>Контроль работы ядра и процессов, возобновление доступа.</p></div>
</div></section>

</div>
