@php
    $levelTones = ['error' => 'danger', 'critical' => 'danger', 'warning' => 'warning', 'notice' => 'info', 'info' => 'neutral'];
    $formatTime = static function (?string $value): string {
        if ($value === null || $value === '' || strlen($value) < 19) {
            return $value ?? '—';
        }

        return substr($value, 8, 2) . '.' . substr($value, 5, 2) . '.' . substr($value, 0, 4)
            . ' ' . substr($value, 11, 8);
    };
    $error = $detail['error'];
    $file = $detail['file'];
    $parsed = $detail['parsed'];
    $occurrences = $detail['occurrences'];
    $fileLine = ($parsed['file'] ?? '') !== ''
        ? $parsed['file'] . (($parsed['line'] ?? 0) > 0 ? ':' . $parsed['line'] : '')
        : '';
    $repeatCount = (int)($detail['repeat_count'] ?? count($occurrences));
@endphp

<div class="md-errors-detail-screen"
     x-data="{
         copied: false,
         async copy() {
             const text = this.$refs.diagnostic.textContent;
             try {
                 await navigator.clipboard.writeText(text);
             } catch (error) {
                 const area = document.createElement('textarea');
                 area.value = text;
                 document.body.appendChild(area);
                 area.select();
                 document.execCommand('copy');
                 area.remove();
             }
             this.copied = true;
             setTimeout(() => { this.copied = false; }, 2000);
         }
     }">
    <pre x-ref="diagnostic" hidden>{{ $detail['copy_text'] }}</pre>

    <div class="md-errors-detail__top">
        <button type="button" class="md-btn md-btn--secondary" yoyo:get="back">
            <i class="ti ti-arrow-left" aria-hidden="true"></i> К списку ошибок
        </button>
        <div class="md-errors-detail__actions">
            <button type="button" class="md-btn md-btn--primary" @click="copy()" aria-live="polite">
                <i class="ti ti-copy" aria-hidden="true"></i>
                <span x-text="copied ? 'Скопировано' : 'Скопировать для AI'">Скопировать для AI</span>
            </button>
            @if ($error['log_file'] !== '')
                <a class="md-btn md-btn--secondary" href="/errors/{{ $error['id'] }}/file">
                    <i class="ti ti-file-text" aria-hidden="true"></i> Исходный файл
                </a>
            @endif
            <form method="post" action="/errors/{{ $error['id'] }}/delete" class="md-inline-form"
                  data-md-confirm="Удалить запись об ошибке?">
                <input type="hidden" name="_token" value="{{ $csrf }}">
                <x-md.button type="submit" variant="danger" icon="trash">Удалить</x-md.button>
            </form>
        </div>
    </div>

    <div class="md-errors-detail__heading">
        <div class="md-errors-detail__eyebrow">Ошибка #{{ $error['id'] }}</div>
        <h2 class="md-errors-detail__title">{{ $error['message'] }}</h2>
        @if (($parsed['error'] ?? '') !== '' && $parsed['error'] !== $error['message'])
            <p class="md-errors-detail__subtitle">{{ $parsed['error'] }}</p>
        @endif
    </div>

    <div class="md-errors-summary">
        <div class="md-errors-summary__item">
            <span class="md-errors-summary__label">Уровень</span>
            <span class="md-errors-summary__value">
                <x-md.badge :tone="$levelTones[$error['level']] ?? 'neutral'">{{ $error['level'] }}</x-md.badge>
            </span>
        </div>
        <div class="md-errors-summary__item">
            <span class="md-errors-summary__label">Повторов</span>
            <span class="md-errors-summary__value">{{ $repeatCount }}</span>
            @if ($repeatCount > 1)
                <span class="md-errors-summary__hint">первый {{ $formatTime($detail['first_at']) }} · последний {{ $formatTime($detail['last_at']) }}</span>
            @endif
        </div>
        <div class="md-errors-summary__item">
            <span class="md-errors-summary__label">Когда</span>
            <span class="md-errors-summary__value">{{ $formatTime($error['created_at']) }}</span>
        </div>
        <div class="md-errors-summary__item">
            <span class="md-errors-summary__label">Request ID</span>
            <span class="md-errors-summary__value">
                @if ($error['request_id'] !== '')
                    <code class="md-table__code">{{ $error['request_id'] }}</code>
                @else
                    —
                @endif
            </span>
        </div>
    </div>

    @if ($file !== null && $file['truncated'])
        <x-md.alert type="warning" title="Файл показан не полностью"
                    text="Показаны первые 512 КБ. Полный файл лежит на сервере и открывается отдельной страницей." />
    @endif

    <x-md.card title="Где возникла" icon="map-pin">
        <dl class="md-errors-meta">
            <div>
                <dt>Класс исключения</dt>
                <dd>{{ ($parsed['class'] ?? '') !== '' ? $parsed['class'] : '—' }}</dd>
            </div>
            <div>
                <dt>Файл и строка</dt>
                <dd>{{ $fileLine !== '' ? $fileLine : '—' }}</dd>
            </div>
            <div>
                <dt>Путь запроса</dt>
                <dd>{{ ($parsed['path'] ?? '') !== '' ? $parsed['path'] : '—' }}</dd>
            </div>
            <div>
                <dt>Request ID в файле</dt>
                <dd>{{ ($parsed['request_id'] ?? '') !== '' ? $parsed['request_id'] : '—' }}</dd>
            </div>
        </dl>
    </x-md.card>

    <x-md.card title="Стек" icon="stack">
        @if (($parsed['frames'] ?? []) !== [])
            <ol class="md-errors-frames">
                @foreach ($parsed['frames'] as $frame)
                    <li class="md-errors-frame">
                        <span class="md-errors-frame__index">#{{ $frame['index'] }}</span>
                        <div class="md-errors-frame__body">
                            @if ($frame['file'] !== '')
                                <code class="md-errors-code">{{ $frame['file'] }}{{ $frame['line'] > 0 ? ':' . $frame['line'] : '' }}</code>
                            @else
                                <code class="md-errors-code">{{ $frame['location'] }}</code>
                            @endif
                            @if ($frame['call'] !== '')
                                <div class="md-errors-frame__call">{{ $frame['call'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @elseif (($parsed['trace'] ?? '') !== '' && $parsed['trace'] !== '(нет)')
            <pre class="md-errors-codeblock">{{ $parsed['trace'] }}</pre>
        @else
            <p class="md-errors-muted">Стек не записан.</p>
        @endif
    </x-md.card>

    <x-md.card title="Контекст" icon="braces">
        @if (($parsed['context_json'] ?? '') !== '')
            <pre class="md-errors-codeblock">{{ $parsed['context_json'] }}</pre>
        @else
            <p class="md-errors-muted">Контекст не записан.</p>
        @endif
    </x-md.card>

    @if ($repeatCount > 1)
        <x-md.card title="Повторы" icon="repeat"
                   subtitle="Записи с тем же уровнем и сообщением. Нажмите строку, чтобы открыть другой повтор.">
            <div class="md-errors-occurrences">
                @foreach (array_slice($occurrences, 0, 100) as $occurrence)
                    <button type="button"
                            class="md-errors-occurrence @if ((int)$occurrence['id'] === (int)$error['id']) is-active @endif"
                            yoyo:get="select({{ $occurrence['id'] }})">
                        <span class="md-errors-occurrence__time">{{ $formatTime($occurrence['created_at']) }}</span>
                        <span class="md-errors-occurrence__id">#{{ $occurrence['id'] }}</span>
                        <span class="md-errors-occurrence__request">{{ $occurrence['request_id'] !== '' ? $occurrence['request_id'] : '—' }}</span>
                    </button>
                @endforeach
            </div>
            @if (count($occurrences) < $repeatCount)
                <p class="md-errors-muted">Показаны последние {{ count($occurrences) }} из {{ $repeatCount }} повторов.</p>
            @endif
        </x-md.card>
    @endif

    @if (($parsed['raw'] ?? '') !== '' || $file !== null)
        <x-md.card title="Исходный файл ошибки" icon="file-code"
                   :subtitle="$file !== null ? $file['name'] : null">
            @if (($parsed['raw'] ?? '') !== '')
                <details class="md-errors-raw">
                    <summary class="md-errors-raw__summary">
                        <i class="ti ti-file-code" aria-hidden="true"></i>
                        Показать полный текст
                        @if ($file !== null)
                            <span class="md-errors-muted">{{ number_format($file['size'] / 1024, 1, ',', ' ') }} КБ</span>
                        @endif
                    </summary>
                    <pre class="md-errors-codeblock md-errors-codeblock--raw">{{ $parsed['raw'] }}</pre>
                </details>
            @else
                <p class="md-errors-muted">Отдельный файл не найден; показаны данные из журнала.</p>
            @endif
        </x-md.card>
    @endif
</div>
