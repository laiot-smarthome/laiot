<section class="md-ui-section" id="blade" data-md-doc-section>
    <div class="md-ui-section__intro">
        <x-md.section-heading
            eyebrow="13 · Blade/Yoyo"
            title="Blade-компоненты"
            description="Те же md-* классы и внешний вид, что у MDJUI, но разметка уже на Blade. Компоненты переносятся по одному, без изменения дизайна."
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
            subtitle="Разметка совпадает с md-card в alpha, поэтому визуально ничего не меняется."
        >
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                <x-md.badge tone="success">готово</x-md.badge>
                <x-md.badge tone="info">Blade</x-md.badge>
                <x-md.badge tone="warning">Yoyo позже</x-md.badge>
            </div>
            <p style="margin:.75rem 0 0;">
                Следующий шаг — интерактивные компоненты на Yoyo, но только после
                переноса статической разметки.
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
        <x-md.alert type="info" title="Blade-компоненты" text="Это та же alpha-разметка, но уже на новом движке." />
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
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.75rem;">
            <x-md.checkbox name="enabled" label="Включено" checked />
            <x-md.switch name="notify" label="Уведомления" />
        </div>
    </div>

    <div class="md-ui-demo__canvas" style="display:block;">
        <x-md.disclosure title="Disclosure" description="Раскрывающийся блок на details/summary." badge="Blade">
            <p>Содержимое раскрывается без нового JavaScript и без изменения вида.</p>
        </x-md.disclosure>
        <div style="margin-top:1rem;">
            <x-md.skeleton :lines="3" />
        </div>
        <x-md.modal id="blade-demo-modal" title="Модалка на Blade">
            <p>Разметка модалки совпадает с alpha; открытие остаётся на Bootstrap.</p>
        </x-md.modal>
        <x-md.drawer id="blade-demo-drawer" title="Drawer на Blade" subtitle="Те же классы md-drawer.">
            <p>Содержимое drawer.</p>
        </x-md.drawer>
    </div>
</section>
