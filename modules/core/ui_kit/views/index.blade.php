<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="palette" /> core · ui</div>
            <h1 class="md-ui-hero__title">UI Kit</h1>
            <p class="md-ui-hero__lede">Alpha-дизайн и Blade-компоненты `x-md.*`. Новый CSS не добавляется.</p>
        </div>
    </section>

    <x-md.card title="Кнопки">
        <div class="md-ui-actions">
            <x-md.button icon="plus">Основная</x-md.button>
            <x-md.button variant="secondary" icon="edit">Вторичная</x-md.button>
            <x-md.button variant="danger" icon="trash">Опасная</x-md.button>
            <x-md.button variant="ghost" icon="x">Ghost</x-md.button>
        </div>
    </x-md.card>

    <x-md.card title="Статусы и бейджи">
        <div class="md-ui-actions">
            <x-md.badge tone="success">ok</x-md.badge>
            <x-md.badge tone="warning">warning</x-md.badge>
            <x-md.badge tone="danger">error</x-md.badge>
            <x-md.status tone="success" label="Работает" />
            <x-md.status tone="warning" label="Проверка" />
        </div>
    </x-md.card>

    <x-md.card title="Поля">
        <div class="md-form">
            <x-md.input name="demo_text" label="Текст" placeholder="Введите значение" />
            <x-md.select name="demo_select" label="Выбор">
                <option>Первый</option>
                <option>Второй</option>
            </x-md.select>
            <x-md.textarea name="demo_textarea" label="Комментарий" rows="3" />
            <x-md.checkbox name="demo_check" value="1" label="Флаг" />
            <x-md.switch name="demo_switch" label="Переключатель" />
        </div>
    </x-md.card>

    <x-md.card title="Сообщения">
        <div class="md-ui-stack">
            <x-md.alert type="info" title="Информация" text="Alpha alert на компоненте." />
            <x-md.alert type="success" title="Готово" text="Операция выполнена." />
            <x-md.alert type="danger" title="Ошибка" text="Что-то пошло не так." />
        </div>
    </x-md.card>

    <div class="md-ui-kpi-grid">
        <x-md.kpi label="Метрика" value="42" icon="activity" tone="info" meta="KPI" />
        <x-md.kpi label="Успех" value="99%" icon="check" tone="success" meta="KPI" />
        <x-md.kpi label="Тревога" value="1" icon="alert-triangle" tone="danger" meta="KPI" />
    </div>

    <x-md.card title="Прогресс и скелетон">
        <x-md.progress :value="64" label="64%" />
        <x-md.skeleton :lines="3" />
    </x-md.card>

    <x-md.activity-feed title="Лента" :items="[
        ['title' => 'Событие', 'text' => 'Alpha activity feed.', 'time' => '12:00', 'tone' => 'primary'],
    ]" />
</div>
