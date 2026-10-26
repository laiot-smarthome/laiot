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
</section>
