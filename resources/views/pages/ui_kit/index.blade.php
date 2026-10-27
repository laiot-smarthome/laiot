<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="palette" /> MyDoMo · дизайн-система</div>
            <h1 class="md-ui-hero__title">UI Kit</h1>
            <p class="md-ui-hero__lede">Компоненты, состояния и готовые решения для интерфейса умного дома.</p>
        </div>
    </section>

    <nav class="md-kit-nav" aria-label="Разделы UI Kit">
        @foreach (['foundations' => 'Основы', 'buttons' => 'Кнопки', 'forms' => 'Формы', 'linkedobject' => 'Связи', 'navigation' => 'Навигация', 'cards' => 'Карточки', 'data' => 'Данные', 'feedback' => 'Диалоги', 'blocks' => 'Лента и код', 'smarthome' => 'Умный дом', 'patterns' => 'Паттерны', 'ai-chat' => 'ИИ-чат'] as $anchor => $caption)
            <a href="#{{ $anchor }}">{{ $caption }}</a>
        @endforeach
    </nav>

    <section id="foundations" class="md-kit-section">
        <x-md.section-heading eyebrow="01 · Основы" title="Дизайн-система" description="Цвета, типографика, иконки и состояния используют общие токены панели." />
        <x-md.card title="Цвета и иконки">
            <x-md.logo />
            <div class="md-ui-actions">
                <x-md.badge tone="primary">Основной</x-md.badge><x-md.badge tone="success">Успех</x-md.badge><x-md.badge tone="warning">Внимание</x-md.badge><x-md.badge tone="danger">Ошибка</x-md.badge><x-md.badge tone="info">Информация</x-md.badge>
            </div>
            <p>Иконки Tabler: <x-md.icon name="home" /> <x-md.icon name="bulb" /> <x-md.icon name="settings" /> <x-md.icon name="chart-line" /> <x-md.icon name="bell" /> <x-md.icon name="wifi" /></p>
        </x-md.card>
        <x-md.card title="Типографика" subtitle="Используйте подходящий уровень заголовка и общие размеры текста.">
            @foreach ([
                ['display', 'Заголовок страницы', 'Крупный заголовок'],
                ['h1', 'Название раздела', 'Заголовок первого уровня'],
                ['h2', 'Заголовок блока', 'Заголовок второго уровня'],
                ['h3', 'Подраздел', 'Заголовок третьего уровня'],
                ['body', 'Основной текст интерфейса', 'Основной текст'],
                ['small', 'Описание и дополнительная информация', 'Вторичный текст'],
                ['caption', 'Состояние и подписи', 'Метки и подписи'],
                ['mono', 'Kitchen.temperature = 22.5', 'Код и значения'],
            ] as [$variant, $sample, $description])
                <div class="md-ui-type-row"><x-md.typography :variant="$variant" as="span" class="md-ui-type-row__sample">{{ $sample }}</x-md.typography><span class="md-ui-type-row__meta">{{ $description }} · {{ $variant }}</span></div>
            @endforeach
        </x-md.card>
        <x-md.card title="Каталог иконок" subtitle="Поиск по Tabler Icons; нажмите на иконку, чтобы скопировать её класс."><x-md.icon-browser /></x-md.card>
    </section>
    <section id="buttons" class="md-kit-section"><x-md.section-heading eyebrow="02 · Действия" title="Кнопки и действия" />
    <x-md.card title="Кнопки">
        <div class="md-ui-actions">
            <x-md.button icon="plus">Основная</x-md.button>
            <x-md.button variant="secondary" icon="edit">Вторичная</x-md.button>
            <x-md.button variant="danger" icon="trash">Опасная</x-md.button>
            <x-md.button variant="ghost" icon="x">Ghost</x-md.button>
        </div>
    </x-md.card>

    <x-md.card title="Размеры и состояния"><div class="md-ui-actions"><x-md.button size="sm">Маленькая</x-md.button><x-md.button>Обычная</x-md.button><x-md.button size="lg">Большая</x-md.button><x-md.button disabled>Недоступна</x-md.button><x-md.icon-button icon="edit" label="Изменить" /></div></x-md.card>
    </section>
    <section id="forms" class="md-kit-section"><x-md.section-heading eyebrow="03 · Формы" title="Поля ввода" description="Нативные контролы, локальная интерактивность и понятные состояния." />
    <x-md.card title="Поля">
        <div class="md-form">
            <x-md.input name="demo_text" label="Текст" placeholder="Введите значение" />
            <x-md.select name="demo_select" label="Выбор">
                <option>Первый</option>
                <option>Второй</option>
            </x-md.select>
            <x-md.select name="demo_search" label="Поиск в списке" searchable placeholder="Выберите помещение" :options="['kitchen' => 'Кухня', 'living' => 'Гостиная', 'bedroom' => 'Спальня']" />
            <x-md.select name="demo_multi" label="Несколько помещений" searchable multiple :options="['kitchen' => 'Кухня', 'living' => 'Гостиная', 'bedroom' => 'Спальня']" :selected="['kitchen', 'living']" />
            <x-md.input type="number" name="demo_number" label="Мощность" value="9" min="0" max="100" suffix="Вт" />
            <x-md.input type="password" name="demo_password" label="Пароль" autocomplete="new-password" password-toggle />
            <x-md.date-time-picker type="date" name="demo_date" label="Дата" />
            <x-md.date-time-picker type="time" name="demo_time" label="Время" />
            <x-md.input type="color" name="demo_color" label="Цвет" value="#4792d1" />
            <x-md.input type="file" name="demo_file" label="Файл" />
            <x-md.input type="search" name="demo_quick_search" label="Поиск" icon="search" placeholder="Название устройства" />
            <x-md.textarea name="demo_textarea" label="Комментарий" rows="3" />
            <x-md.checkbox name="demo_check" value="1" label="Флаг" />
            <x-md.switch name="demo_switch" label="Переключатель" />
            <x-md.input name="demo_error" label="Ошибка" value="Неверное значение" error="Проверьте значение поля" />
            <x-md.input name="demo_disabled" label="Недоступное поле" value="Недоступно" disabled />
            <x-md.radio name="demo_radio" value="auto" label="Автоматически" checked />
            <x-md.radio name="demo_radio" value="manual" label="Вручную" />
            <x-md.range name="demo_range" label="Яркость" :value="65" unit="%" />
        </div>
    </x-md.card>

    <x-md.card title="Выбор карточками"><x-md.choice-group name="demo_mode" label="Режим" selected="auto" :options="['auto' => ['label' => 'Автоматически', 'icon' => 'sparkles', 'description' => 'По расписанию'], 'manual' => ['label' => 'Вручную', 'icon' => 'hand-click', 'description' => 'Управление пользователем']]" /></x-md.card>
    <x-md.card title="Загрузка файлов"><x-md.file-upload name="kit_files" label="Документы" multiple hint="Можно выбрать несколько файлов." /></x-md.card>
    <x-md.card title="Выбор папки проекта" subtitle="Серверный выбор каталога доступен пользователю с правом управления настройками.">@yoyo('folder-picker', ['name' => 'demo_folder'])</x-md.card>
    </section>
    <section id="linkedobject" class="md-kit-section"><x-md.section-heading eyebrow="04 · Связи" title="Объект, свойство и метод" /><x-md.card title="Связь объектов"><x-md.linked-object name="kit_object" label="Объект" property-name="kit_property" method-name="kit_method" /></x-md.card></section>
    <section id="navigation" class="md-kit-section"><x-md.section-heading eyebrow="05 · Навигация" title="Навигация и всплывающие элементы" />
    <x-md.card title="Хлебные крошки" subtitle="Показывают путь к текущей странице; последний пункт не является ссылкой.">
        <x-md.breadcrumbs :items="[['label' => 'Панель', 'href' => '/admin'], ['label' => 'Классы', 'href' => '/classes'], ['label' => 'Датчики']]" />
    </x-md.card>
    <x-md.card title="Всплывающие элементы">
        <div class="md-ui-actions">
            <x-md.dropdown label="Действия">
                <button type="button">Редактировать</button>
                <button type="button">Копировать</button>
            </x-md.dropdown>
            <x-md.tooltip text="Дополнительная информация"><x-md.button variant="secondary">Подсказка</x-md.button></x-md.tooltip>
        </div>
    </x-md.card>
    <x-md.card title="Командная палитра и контекстное меню">
        <div class="md-ui-actions" x-data><x-md.button variant="secondary" @click="$store.mdModal.show('kit-commands')">Открыть палитру</x-md.button>
            <x-md.context-menu label="Пример контекстного меню"><x-slot:trigger><span class="md-kit-context__trigger">Нажмите правой кнопкой или Shift+F10</span></x-slot:trigger><button type="button">Редактировать</button><button type="button">Копировать</button></x-md.context-menu>
        </div>
    </x-md.card>
    <x-md.command-palette id="kit-commands" :items="[['label' => 'Формы', 'description' => 'Поля и состояния', 'href' => '#forms'], ['label' => 'Таблицы', 'description' => 'Данные и поиск', 'href' => '#data'], ['label' => 'Устройства', 'description' => 'Компоненты умного дома', 'href' => '#smarthome']]" />

    <x-md.card title="Вкладки"><x-md.tabs label="Пример вкладок" active="first" :items="[['id' => 'first', 'label' => 'Основное', 'href' => '#navigation'], ['id' => 'second', 'label' => 'События', 'href' => '#data']]" /></x-md.card></section>
    <section id="cards" class="md-kit-section"><x-md.section-heading eyebrow="06 · Контейнеры" title="Карточки и метрики" />
    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Метрика" value="42" icon="activity" tone="info" meta="KPI" />
        <x-md.kpi label="Успех" value="99%" icon="check" tone="success" meta="KPI" />
        <x-md.kpi label="Тревога" value="1" icon="alert-triangle" tone="danger" meta="KPI" />
    </div>

    <x-md.card title="Прогресс и скелетон">
        <x-md.progress :value="64" label="64%" />
        <x-md.skeleton :lines="3" />
    </x-md.card>

    <x-md.card title="Раскрывающийся блок"><x-md.disclosure title="Дополнительные параметры" description="Открывается мышью и клавиатурой"><p>Содержимое блока.</p></x-md.disclosure></x-md.card></section>
    <section id="data" class="md-kit-section"><x-md.section-heading eyebrow="07 · Данные" title="Таблицы и состояния" />
    <x-md.card title="Статусы и бейджи">
        <div class="md-ui-actions">
            <x-md.badge tone="success">ok</x-md.badge>
            <x-md.badge tone="warning">warning</x-md.badge>
            <x-md.badge tone="danger">error</x-md.badge>
            <x-md.status state="online" label="Работает" />
            <x-md.status state="warning" label="Проверка" />
        </div>
    </x-md.card>

    <x-md.card title="Таблица с поиском и сортировкой" subtitle="Tabulator: поиск, сортировка и страницы работают локально.">
        <x-md.data-table label="Пример устройств" :columns="[['key' => 'name', 'label' => 'Устройство'], ['key' => 'room', 'label' => 'Комната'], ['key' => 'status', 'label' => 'Состояние']]" :rows="[['name' => 'Основной свет', 'room' => 'Кухня', 'status' => 'Онлайн'], ['name' => 'Термостат', 'room' => 'Гостиная', 'status' => 'Онлайн'], ['name' => 'Датчик движения', 'room' => 'Прихожая', 'status' => 'Офлайн']]" :page-size="2" />
    </x-md.card>
    <x-md.card title="Серверная фильтрация на Yoyo" subtitle="Для данных, которые получает и фильтрует сервер.">@yoyo('ui-kit-devices')</x-md.card>
    <x-md.card title="Журнал событий" subtitle="Реальные события с поиском и фильтрами типа и статуса.">@yoyo('events-table')<div class="md-ui-actions"><a class="md-btn md-btn--secondary md-btn--sm" href="/events">Открыть журнал</a></div></x-md.card>
    <x-md.card title="Дерево объектов" subtitle="Раскрывайте группы и переходите к объектам."><x-md.object-tree :items="[
        ['label' => 'Дом', 'open' => true, 'children' => [
            ['label' => 'Кухня', 'open' => true, 'children' => [['label' => 'Свет', 'icon' => 'bulb'], ['label' => 'Температура', 'icon' => 'temperature']]],
            ['label' => 'Гостиная', 'children' => [['label' => 'Термостат', 'icon' => 'temperature']]],
        ]],
    ]" /></x-md.card>
    <x-md.card title="Загрузка и отсутствие данных"><div class="md-ui-card-grid"><div><div class="md-table__loading" role="status"><span class="md-spinner" aria-hidden="true"></span> Загружаем устройства…</div><x-md.skeleton :lines="3" /></div><x-md.empty-state title="Пока нет данных" text="Устройства появятся здесь после подключения." compact /></div></x-md.card>
    <x-md.card title="Аватары и пустое состояние"><x-md.avatar-group :items="['Анна Иванова', 'Михаил Сидоров', 'Сергей Петров']" /><x-md.empty-state title="Устройства не найдены" text="Измените условия поиска или добавьте устройство." compact /></x-md.card>
    <x-md.chart-card title="Температура за сутки" unit="°C" :points="[['t' => 1735689600000, 'v' => 21], ['t' => 1735711200000, 'v' => 22], ['t' => 1735732800000, 'v' => 23], ['t' => 1735754400000, 'v' => 21.5]]" />
    <x-md.card title="Компактный график"><x-md.sparkline label="Активность" :points="[['t' => 1735689600000, 'v' => 3], ['t' => 1735711200000, 'v' => 8], ['t' => 1735732800000, 'v' => 5]]" /></x-md.card>
    @php($tableExample = '<' . 'x-md.data-table :columns="$columns" :rows="$rows" label="Устройства" />')
    <x-md.code-sample title="Пример таблицы" :code="$tableExample" />
    </section>
    <section id="feedback" class="md-kit-section"><x-md.section-heading eyebrow="08 · Обратная связь" title="Сообщения и диалоги" />
    <x-md.card title="Сообщения">
        <div class="md-ui-alert-stack">
            <x-md.alert type="info" title="Информация" text="Информационное сообщение." />
            <x-md.alert type="success" title="Готово" text="Операция выполнена." />
            <x-md.alert type="danger" title="Ошибка" text="Что-то пошло не так." />
        </div>
    </x-md.card>
    <x-md.card title="Всплывающее уведомление"><div x-data><x-md.button variant="secondary" @click="$store.mdToasts.push({title: 'Сохранено', text: 'Изменения применены', tone: 'success'})">Показать уведомление</x-md.button></div></x-md.card>

    <x-md.card title="Модальное окно и боковая панель"><div class="md-ui-actions" x-data><x-md.button @click="$store.mdModal.show('kit-dialog')">Открыть диалог</x-md.button><x-md.button variant="secondary" @click="$store.mdDrawer.show('kit-drawer')">Открыть панель</x-md.button></div></x-md.card>
    <x-md.card title="История и цепочка событий"><div class="md-ui-actions"><x-md.history-drawer id="kit-history" title="История датчика"><x-md.chart-card title="Температура" unit="°C" :points="[['t' => 1735689600000, 'v' => 21], ['t' => 1735711200000, 'v' => 22]]" /></x-md.history-drawer><x-md.trace-drawer id="kit-trace" :nodes="[['type' => 'property', 'name' => 'Motion.value', 'status' => 'Изменено', 'children' => [['type' => 'method', 'name' => 'Motion.onChange', 'status' => 'Выполнено']]]]" /></div></x-md.card>
    <x-md.modal id="kit-dialog" title="Новое устройство"><x-md.input label="Название" name="kit_modal_name" hint="Введите название устройства" /></x-md.modal>
    <x-md.drawer id="kit-drawer" title="Устройство" subtitle="Пример боковой панели"><p>Здесь могут находиться подробности и действия устройства.</p></x-md.drawer>
    </section>
    <section id="blocks" class="md-kit-section"><x-md.section-heading eyebrow="09 · Блоки" title="Лента, подсказки и код" />
    <x-md.hint title="Как работает история" text="Изменения свойств доступны в карточке объекта." />
    <x-md.activity-feed title="Лента" :items="[
        ['title' => 'Событие', 'text' => 'Значение датчика обновлено.', 'time' => '12:00', 'tone' => 'primary'],
    ]" />

    <x-md.card title="Редактор кода" subtitle="Подсветка PHP, поиск, отмена и повтор изменений."><x-md.code name="kit_code" label="Метод" :value="'// Включить свет при движении' . PHP_EOL . 'if ($this->get(\'motion\')) {' . PHP_EOL . '    $this->set(\'light\', 1);' . PHP_EOL . '}'" /></x-md.card>
    <x-md.code-sample title="Просмотр кода PHP" language="php" :code="'// Записать значение свойства' . PHP_EOL . 'sg(\'Kitchen.light\', 1);'" />
    <x-md.json-view title="Просмотр JSON" :data="['object' => 'Kitchen', 'property' => 'temperature', 'value' => 22.5, 'online' => true]" />
    </section>
    <section id="smarthome" class="md-kit-section"><x-md.section-heading eyebrow="10 · Умный дом" title="Устройства и датчики" />
        <div class="md-ui-card-grid"><x-md.device-card name="Основной свет" room="Кухня" type="Освещение" icon="bulb" value="65" unit="%" updated="Только что" connection="Zigbee"><x-md.switch label="Включён" checked /></x-md.device-card><x-md.sensor label="Температура" value="23,1" unit="°C" icon="temperature" hint="Гостиная" /><x-md.sensor label="Влажность" value="48" unit="%" icon="droplet" tone="info" /></div>
        <div class="md-ui-card-grid">
            <x-md.light-control name="kit_light" label="Освещение" :value="65" enabled />
            <x-md.thermostat name="kit_temperature" :temperature="23.1" :target="22" />
            <x-md.scene-card name="Доброе утро" description="Свет и климат по расписанию"><x-md.button variant="secondary" size="sm">Запустить</x-md.button></x-md.scene-card>
        </div>
        <div class="md-ui-card-grid">
            <x-md.room-card name="Гостиная" :devices="8" :online="7" :temperature="22.5" :humidity="48" />
            <x-md.energy-widget today="8,4" yesterday="9,1" :usage="[['label' => 'Освещение', 'value' => 35], ['label' => 'Отопление', 'value' => 60]]" />
            <x-md.cycle-card name="Обработчик событий" state="online" description="Фоновый процесс" updated="только что" />
        </div>
        <x-md.card title="Автоматизации и подключения"><x-md.automation-row name="Утренний сценарий" description="Будни в 07:00" last="сегодня" /><x-md.connection-status label="Zigbee" state="online" detail="Координатор подключён" /></x-md.card>
    </section>
    <section id="patterns" class="md-kit-section"><x-md.section-heading eyebrow="11 · Паттерны" title="Построение страниц" /><x-md.card title="Каркас раздела" subtitle="Заголовок, показатели, фильтры и основное содержимое"><p>Собирайте страницы из общих компонентов и показывайте ошибки рядом с полями.</p></x-md.card></section>
    <section id="ai-chat" class="md-kit-section">
        <x-md.section-heading eyebrow="12 · ИИ" title="Компоненты сообщений" />
        <x-md.card title="Сущности"><div class="md-ui-actions"><x-md.entity type="object" name="Kitchen" /><x-md.entity type="property" name="Kitchen.Temperature" /><x-md.entity type="method" name="Kitchen.onChange" /><x-md.entity type="script" name="Morning" /></div><p><x-md.entity-text text="Свойство [[property:Kitchen.Temperature|Температура]] обновлено сценарием [[script:Morning]]." /></p></x-md.card>
        <x-md.call-flow title="Свет по движению" started-at="23:41:07.120" duration="64 мс" :nodes="[
            ['kind' => 'property', 'entity' => ['type' => 'property', 'name' => 'Motion.value'], 'time' => '23:41:07.120', 'actor' => 'Датчик движения · MQTT', 'before' => '0', 'after' => '1', 'status' => 'ok', 'children' => [
                ['kind' => 'method', 'entity' => ['type' => 'method', 'name' => 'Motion.onChange'], 'time' => '23:41:07.125', 'offset' => '+5 мс', 'note' => 'Запущен обработчик изменения', 'status' => 'ok', 'children' => [
                    ['kind' => 'condition', 'title' => 'Можно включать свет', 'expression' => 'Motion.value == 1 && Security.status == 0', 'branches' => [
                        ['label' => 'Да', 'taken' => true, 'nodes' => [
                            ['kind' => 'script', 'entity' => ['type' => 'script', 'name' => 'Свет по движению'], 'time' => '23:41:07.170', 'status' => 'ok', 'children' => [
                                ['kind' => 'property', 'entity' => ['type' => 'property', 'name' => 'Light.status'], 'time' => '23:41:07.184', 'offset' => '+64 мс', 'actor' => 'Сценарий «Свет по движению»', 'before' => '0', 'after' => '1', 'status' => 'ok'],
                            ]],
                        ]],
                        ['label' => 'Нет', 'taken' => false, 'empty_text' => 'Свет не включается'],
                    ]],
                ]],
            ]],
        ]" />
    </section>
    <x-md.card title="Yoyo: серверный счётчик" subtitle="Blade + Yoyo, состояние на сервере">
        <div class="md-ui-demo__canvas">@yoyo('counter')</div>
    </x-md.card>

    @yoyo_scripts
    <script>
        document.body.addEventListener('htmx:configRequest', function (event) {
            const meta = document.querySelector('meta[name="csrf-token"]');
            event.detail.headers['X-CSRF-Token'] = meta ? meta.content : '';
        });
    </script>
</div>
