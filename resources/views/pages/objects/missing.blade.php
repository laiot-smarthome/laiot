<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero eyebrow="Объект" icon="box" title="Объект не найден"
                    lede="Возможно, запись удалена или ссылка устарела.">
        <x-slot:actions>
            <x-md.button href="/objects" variant="secondary" icon="arrow-left">К объектам</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.empty-state icon="search" title="Записи нет"
                      text="Объект мог быть удалён. Вернитесь в каталог и выберите другой." />
</div>
