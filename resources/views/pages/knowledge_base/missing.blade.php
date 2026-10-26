<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="book" /> База знаний</div>
            <h1 class="md-ui-hero__title">Материал не найден</h1>
        </div>
    </section>

    <x-md.card>
        <x-md.empty-state
            title="Нет такого материала"
            :text="'Документ «' . $doc . '» не найден в docs/.'"
            icon="book"
        />
        <div class="md-ui-actions">
            <x-md.button href="/module/knowledge_base" variant="secondary" icon="arrow-left">К списку</x-md.button>
        </div>
    </x-md.card>
</div>
