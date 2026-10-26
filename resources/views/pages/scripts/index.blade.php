<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Автоматизация"
        icon="file-code"
        title="Скрипты"
        lede="Сценарии на PHP: запускаются вручную, по расписанию или из других сценариев и API."
        :meta="[
            ['icon' => 'file-code', 'label' => 'Скриптов', 'value' => count($scripts)],
            ['icon' => 'folder', 'label' => 'Категорий', 'value' => count($categories)],
        ]">
        <x-slot:actions>
            <x-md.button href="/scripts/export" variant="secondary" icon="download">Экспорт</x-md.button>
            <x-md.button href="/scripts/import" variant="secondary" icon="upload">Импорт</x-md.button>
            <x-md.button href="/scripts/create" icon="plus">Новый скрипт</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    @if ($message !== '')
        <x-md.alert type="success" :text="$message" />
    @endif
    @if ($error !== '')
        <x-md.alert type="danger" title="Действие не выполнено" :text="$error" />
    @endif

    <x-md.table label="Скрипты">
        <x-slot:toolbar>
            <form class="md-ui-search" method="get" action="/scripts" role="search">
                <input class="md-input" type="search" name="q" value="{{ $search }}"
                       placeholder="Поиск по названию и описанию" aria-label="Поиск скриптов" autocomplete="off">
                <select class="md-table__filter" name="category_id" aria-label="Фильтр по категории" onchange="this.form.submit()">
                    <option value="0">Все категории</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category['id'] }}" @selected($categoryId === $category['id'])>{{ $category['title'] }}</option>
                    @endforeach
                </select>
                <button class="md-btn md-btn--secondary" type="submit">
                    <i class="ti ti-search" aria-hidden="true"></i><span class="md-btn__label">Найти</span>
                </button>
                @if ($search !== '' || $categoryId !== 0)
                    <a class="md-btn md-btn--ghost" href="/scripts">Сбросить</a>
                @endif
            </form>
            <div class="md-table__meta">Найдено: {{ count($scripts) }}</div>
        </x-slot:toolbar>

        @if ($scripts === [])
            <x-slot:empty>
                <div class="md-empty">
                    <span class="md-empty__icon"><i class="ti ti-file-code" aria-hidden="true"></i></span>
                    <div class="md-empty__title">{{ $search !== '' || $categoryId !== 0 ? 'Скрипты не найдены' : 'Скриптов пока нет' }}</div>
                    <p class="md-empty__text">{{ $search !== '' || $categoryId !== 0 ? 'Измените запрос или сбросьте фильтр.' : 'Создайте первый сценарий — он сможет менять значения и вызывать методы.' }}</p>
                    <div class="md-empty__actions">
                        <a class="md-btn md-btn--primary" href="/scripts/create">
                            <i class="ti ti-plus" aria-hidden="true"></i><span class="md-btn__label">Новый скрипт</span>
                        </a>
                    </div>
                </div>
            </x-slot:empty>
        @else
            <x-slot:head>
                <th scope="col">Скрипт</th>
                <th scope="col">Категория</th>
                <th scope="col">Запуск</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($scripts as $script)
                    <tr>
                        <td>
                            <div class="md-table__name">
                                <span class="md-table__icon"><i class="ti ti-file-code" aria-hidden="true"></i></span>
                                <div>
                                    <a href="/scripts/{{ $script['id'] }}/edit"><strong>{{ $script['title'] }}</strong></a>
                                    @if ($script['description'] !== '')
                                        <small>{{ $script['description'] }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if (!empty($categoryNames[$script['category_id']]))
                                <x-md.badge tone="outline">{{ $categoryNames[$script['category_id']] }}</x-md.badge>
                            @else
                                <span class="md-table__muted">Без категории</span>
                            @endif
                        </td>
                        <td>
                            @if ($script['run_periodically'])
                                <x-md.badge tone="success">по расписанию</x-md.badge>
                            @else
                                <x-md.badge tone="neutral">вручную</x-md.badge>
                            @endif
                        </td>
                        <td>
                            <div class="md-table__actions">
                                <x-md.icon-button href="/scripts/{{ $script['id'] }}/edit" icon="pencil" label="Открыть скрипт" size="sm" />
                                <form method="post" action="/scripts/{{ $script['id'] }}/delete" class="md-inline-form"
                                      data-md-confirm="Удалить скрипт «{{ $script['title'] }}»?">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <x-md.icon-button type="submit" icon="trash" label="Удалить скрипт" variant="danger" size="sm" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        @endif
    </x-md.table>

    <x-md.card title="Категории сценариев" subtitle="Категории помогают группировать сценарии и фильтровать список">
        <form method="post" action="/scripts/categories" class="md-ui-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <div class="md-ui-form-grid">
                <x-md.input name="title" label="Новая категория" placeholder="Освещение, Климат, Уведомления"
                            required hint="До 191 символа." />
            </div>
            <div class="md-ui-form-actions">
                <x-md.button type="submit" icon="plus">Добавить категорию</x-md.button>
            </div>
        </form>

        @if ($categories === [])
            <x-md.empty-state icon="folder" title="Категорий нет"
                              text="Создайте категорию, чтобы фильтровать сценарии по назначению." compact />
        @else
            <x-md.table label="Категории сценариев">
                <x-slot:head>
                    <th scope="col">Категория</th>
                    <th scope="col">Сценариев</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
                </x-slot:head>
                <x-slot:body>
                    @foreach ($categories as $category)
                        <tr>
                            <td>
                                <form method="post" action="/scripts/categories/{{ $category['id'] }}" class="md-inline-form">
                                    <input type="hidden" name="_token" value="{{ $csrf }}">
                                    <input class="md-input" type="text" name="title" value="{{ $category['title'] }}"
                                           aria-label="Название категории {{ $category['title'] }}">
                                    <x-md.button type="submit" variant="secondary" size="sm" icon="check">Сохранить</x-md.button>
                                </form>
                            </td>
                            <td>
                                <a href="/scripts?category_id={{ $category['id'] }}">{{ $categoryCounts[$category['id']] ?? 0 }}</a>
                            </td>
                            <td>
                                <div class="md-table__actions">
                                    <form method="post" action="/scripts/categories/{{ $category['id'] }}/delete" class="md-inline-form"
                                          data-md-confirm="Удалить категорию «{{ $category['title'] }}»? Сценарии останутся без категории.">
                                        <input type="hidden" name="_token" value="{{ $csrf }}">
                                        <x-md.icon-button type="submit" icon="trash" label="Удалить категорию" variant="danger" size="sm" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-md.table>
        @endif
    </x-md.card>
</div>
