<section class="md-ui-section" id="blade" data-md-doc-section>
    <div class="md-ui-section__intro">
        <x-md.section-heading
            eyebrow="13 · Blade/Yoyo"
            title="Blade-компоненты"
            description="Blade-компоненты нового ядра в alpha-визуале. Alpha-разметка — ориентир, а не реализация."
        />
        <span class="md-ui-section__kicker">x-md.*</span>
    </div>

    <div class="md-ui-demo__canvas">
        <x-md.button variant="primary" icon="check2">Сохранить</x-md.button>
        <x-md.button variant="secondary">Отмена</x-md.button>
        <x-md.button variant="outline" icon="sliders">Настроить</x-md.button>
        <x-md.button variant="ghost" icon-after="arrow-right">Подробнее</x-md.button>
        <x-md.button variant="danger" icon="trash3">Удалить</x-md.button>
    </div>

    <div class="md-ui-demo__canvas" style="display:block;">
        <x-md.card
            eyebrow="Blade"
            title="Карточка на новом движке"
            subtitle="Визуал alpha сохранён, разметка и поведение — новые."
        >
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                <x-md.badge tone="success">готово</x-md.badge>
                <x-md.badge tone="info">Blade</x-md.badge>
                <x-md.badge tone="warning">Yoyo</x-md.badge>
            </div>
            <p style="margin:.75rem 0 0;">
                Yoyo-компоненты подключаются отдельной секцией на странице
                `/module/ui_kit`; здесь — статическая Blade-разметка.
            </p>
        </x-md.card>
    </div>

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Объекты" value="128" icon="home" tone="primary" meta="В системе" />
        <x-md.kpi label="Онлайн" value="96" icon="wifi" tone="success" meta="За последний час" />
        <x-md.kpi label="Ошибки" value="2" icon="alert-triangle" tone="warning" meta="Требуют внимания" />
        <x-md.kpi label="База" value="42" unit="МБ" icon="database" tone="info" meta="Размер" />
    </div>

    <div class="md-ui-demo__canvas" style="display:block;">
        <x-md.alert id="blade-demo-alert" type="info" title="Blade-компоненты" text="Компоненты нового ядра; alpha используется только как визуальный ориентир." dismissible />
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin:1rem 0;">
            <x-md.status state="online" />
            <x-md.status state="warning" label="Проверка" />
            <x-md.status state="offline" />
            <x-md.avatar name="Admin User" />
            <x-md.avatar name="Сергей" tone="primary" />
        </div>
        <x-md.progress :value="64" label="Перенос компонентов" show-value />
        <x-md.empty-state icon="inbox" title="Пока пусто" text="Empty-state тоже перенесён без изменения вида." />
    </div>

    <div class="md-ui-demo__canvas" style="display:block;">
        <x-md.field label="Название" for="blade-name" hint="Подсказка остаётся прежней.">
            <x-md.input name="name" id="blade-name" placeholder="Например, гостиная" />
        </x-md.field>
        <x-md.select
            name="room"
            label="Комната"
            :options="['living' => 'Гостиная', 'kitchen' => 'Кухня', 'bedroom' => 'Спальня']"
            selected="living"
        />
        <x-md.textarea name="comment" label="Комментарий" placeholder="Текст" />
        <x-md.code name="demo_code" label="PHP-код" value="echo 'Hello';" rows="4" hint="Нумерация строк на Alpine, без CodeMirror." />
        <x-md.linked-object name="demo_object" label="Связанный объект"
                            property-name="demo_property" method-name="demo_method"
                            hint="Поиск объекта и выбор свойства/метода класса; ядровой Alpine-компонент." />
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.75rem;">
            <x-md.checkbox name="enabled" label="Включено" checked />
            <x-md.switch name="notify" label="Уведомления" />
        </div>
    </div>

    <div class="md-ui-demo__canvas" style="display:block;">
        <x-md.hint
            id="blade-demo-hint"
            tone="tip"
            title="Blade + Alpine"
            text="Визуал alpha, поведение новое: без jQuery и Bootstrap JS."
            :points="['Blade-компоненты', 'Alpine для интерактива', 'Yoyo для серверных блоков']"
            dismissible
        />
        <div style="margin-top:1rem;">
            <x-md.activity-feed
                title="Лента событий"
            subtitle="Blade-версия activity feed"
            eyebrow="Blade"
            :items="[
                ['title' => 'Объект обновлён', 'text' => 'Гостиная · температура 23°C', 'time' => '12:40', 'date' => 'Сегодня', 'tone' => 'success', 'actor' => 'Сценарий'],
                ['title' => 'Ошибка устройства', 'text' => 'Кухня · нет связи', 'time' => '12:35', 'date' => 'Сегодня', 'tone' => 'danger', 'badge' => 'важно', 'badge_tone' => 'danger'],
            ]"
            />
        </div>
        <div style="margin-top:1rem;">
            <x-md.disclosure title="Disclosure" description="Раскрывающийся блок на details/summary." badge="Blade">
            <p>Содержимое раскрывается без нового JavaScript и без изменения вида.</p>
        </x-md.disclosure>
        </div>
        <div style="margin-top:1rem;">
            <x-md.skeleton :lines="3" />
        </div>
        <div x-data style="margin-top:1rem; display:flex; gap:.5rem; flex-wrap:wrap;">
            <x-md.button type="button" icon="maximize" @click="$store.mdModal.show('blade-demo-modal')">Открыть модалку</x-md.button>
            <x-md.button type="button" variant="secondary" icon="layout-sidebar" @click="$store.mdDrawer.show('blade-demo-drawer')">Открыть drawer</x-md.button>
        </div>
        <x-md.modal id="blade-demo-modal" title="Модалка на Alpine">
            <p>Состояние хранится в Alpine store, Bootstrap JS не нужен.</p>
        </x-md.modal>
        <x-md.drawer id="blade-demo-drawer" title="Drawer на Alpine" subtitle="Те же md-* классы, новое поведение.">
            <p>Содержимое drawer.</p>
        </x-md.drawer>
    </div>
</section>
