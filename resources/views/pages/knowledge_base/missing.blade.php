<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="База знаний"
        icon="book"
        title="Материал не найден"
        lede="Документ мог быть переименован или удалён из docs/."
        :meta="[['icon' => 'file-text', 'label' => 'Запрошен', 'value' => $doc]]">
        <x-slot:actions>
            <x-md.button href="/knowledge-base" variant="secondary" icon="arrow-left">К базе знаний</x-md.button>
        </x-slot:actions>
    </x-md.page-hero>

    <x-md.empty-state icon="search" title="Такого документа нет"
                      text="Проверьте адрес или вернитесь к списку материалов." />
</div>
