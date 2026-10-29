<div class="md-upd-admin" x-data="{busy: ''}"
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
            <button type="button" yoyo:get="changeChannel('main')" data-update-action="changeChannel" x-bind:disabled="busy !== ''" aria-pressed="{{ $channel === 'main' ? 'true' : 'false' }}"><x-md.icon name="shield-check" /><span>main <small>Стабильный</small></span></button>
            <button type="button" yoyo:get="changeChannel('beta')" data-update-action="changeChannel" x-bind:disabled="busy !== ''" aria-pressed="{{ $channel === 'beta' ? 'true' : 'false' }}"><x-md.icon name="flask" /><span>beta <small>Тестовый</small></span></button>
        </div>
        <x-md.button variant="secondary" yoyo:post="refresh" data-update-action="refresh" x-bind:disabled="busy !== ''" class="md-upd-refresh"><span x-show="busy !== 'refresh'"><span class="md-upd-button-content"><x-md.icon name="refresh" /> Проверить обновления</span></span><span x-show="busy === 'refresh'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Проверка обновлений…</span></span></x-md.button>
    </div>
    <div class="md-upd-loading" role="status" x-show="busy === 'refresh'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Получение списка релизов…</span></div>
    <div class="md-upd-loading" role="status" x-show="busy === 'changeChannel'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Загрузка выбранного канала…</span></div>
    <div class="md-upd-loading" role="status" x-show="busy === 'inspect'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Проверка релиза и совместимости модулей…</span></div>
    <div class="md-upd-release-results" x-bind:class="{'is-loading': busy !== ''}">
    @if ($releases === [])
        <div class="md-upd-no-releases"><span class="md-upd-no-releases__icon"><x-md.icon name="planet" /></span><h3>Нет доступных релизов</h3><p>Обновите список выпусков или выберите другой канал.</p></div>
    @else
        <div class="md-upd-release-list">
            @foreach ($releases as $release)
                <button type="button" class="md-upd-release-option" data-selected="{{ ($preview['manifest']['id'] ?? '') === $release['id'] ? 'true' : 'false' }}" yoyo:get="inspect('{{ $release['id'] }}')" data-update-action="inspect" x-bind:disabled="busy !== ''" aria-label="Посмотреть и проверить {{ $release['id'] }}">
                    <span class="md-upd-release-option__icon"><x-md.icon name="package" /></span>
                    <span class="md-upd-release-option__info"><strong>{{ $release['id'] }}</strong><span>{{ $release['title'] ?? 'Релиз системы' }}</span></span>
                    <span class="md-upd-release-option__date">{{ isset($release['published_at']) && strtotime($release['published_at']) !== false ? date('d.m.Y', strtotime($release['published_at'])) : 'Сохранён локально' }}</span>
                    <span class="md-upd-release-option__action">{{ ($preview['manifest']['id'] ?? '') === $release['id'] ? 'Выбрано' : 'Посмотреть' }} <x-md.icon name="arrow-up-right" /></span>
                </button>
            @endforeach
        </div>
    @endif
    </div>
</section>

@if ($preview !== [])
    <section class="md-upd-preview">
        <div class="md-upd-section-head"><div><span class="md-upd-kicker">СВЕДЕНИЯ О РЕЛИЗЕ</span><h2>{{ $preview['manifest']['id'] }}</h2></div><x-md.badge :tone="$preview['ready'] ? 'success' : 'warning'" dot>{{ $preview['ready'] ? 'Проверки пройдены' : 'Обнаружены замечания' }}</x-md.badge></div>
        <div class="md-upd-preview-grid"><div class="md-upd-release-notes"><span class="md-upd-mini-label">ИЗМЕНЕНИЯ В РЕЛИЗЕ</span>@if ($notes !== '')<div class="md-prose">{!! $notes !!}</div>@else<p>У этого выпуска пока нет описания изменений.</p>@endif</div>
            <div class="md-upd-preflight"><h3><x-md.icon name="clipboard-check" /> Проверка перед установкой</h3><ul>@foreach ($preview['checks'] as $check)<li data-ok="{{ $check['ok'] ? 'true' : 'false' }}"><x-md.icon :name="$check['ok'] ? 'circle-check' : 'alert-circle'" /><div><strong>{{ $check['title'] }}</strong><p>{{ $check['detail'] }}</p></div></li>@endforeach</ul></div>
        </div>
        <div class="md-upd-departure"><div class="md-upd-departure__note"><x-md.icon name="coffee" /><div><strong>На время обновления система будет приостановлена</strong><p>Перед изменениями остановятся запись и фоновые процессы. После сохранения снимка БД начнётся установка релиза.</p></div></div>
            @if ($preview['snapshot'] !== [])<div class="md-upd-data-warning"><x-md.icon name="history" /><div><strong>Вернём базу к {{ $preview['snapshot']['created_at'] }}</strong><p>Более новые пользовательский код, сценарии, привязки, настройки и другие данные MySQL будут потеряны. Сторонние модули и файлы var сохранятся.</p></div></div>@endif
            <form yoyo:post="start" data-update-action="start"><fieldset class="md-upd-start-form" x-bind:disabled="busy !== ''"><input type="hidden" name="release" value="{{ $preview['manifest']['id'] }}"><x-md.checkbox name="confirmed" label="Понимаю последствия и разрешаю переключение версии" required />
                @if ($error !== '')<p role="alert">{{ $error }}</p>@endif
                <x-md.button type="submit" :disabled="!$preview['ready'] || in_array($operation['status'] ?? '', ['queued','running','failed','interrupted'], true)"><span x-show="busy !== 'start'">{{ $preview['snapshot'] !== [] ? 'Восстановить версию' : 'Установить релиз' }}</span><span x-show="busy === 'start'" x-cloak><span class="md-upd-button-content"><span class="md-spinner" aria-hidden="true"></span> Подготовка обновления…</span></span></x-md.button>
            </fieldset></form>
        </div>
    </section>
@endif

<section class="md-upd-how"><div class="md-upd-section-head"><div><span class="md-upd-kicker">ПОРЯДОК ВЫПОЛНЕНИЯ</span><h2>Как выполняется обновление</h2></div></div><div class="md-upd-how-grid">
    <div><span>01</span><x-md.icon name="scan" /><h3>Проверка окружения</h3><p>Проверка PHP, доступа к файлам и совместимости модулей.</p></div>
    <div><span>02</span><x-md.icon name="database-heart" /><h3>Резервная копия</h3><p>Создание последнего снимка MySQL текущего релиза.</p></div>
    <div><span>03</span><x-md.icon name="rocket" /><h3>Установка релиза</h3><p>Обновление ядра с сохранением файлов и сторонних модулей.</p></div>
    <div><span>04</span><x-md.icon name="home-check" /><h3>Проверка запуска</h3><p>Контроль работы ядра и процессов, возобновление доступа.</p></div>
</div></section>

</div>
