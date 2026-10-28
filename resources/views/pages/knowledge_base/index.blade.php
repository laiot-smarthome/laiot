<div class="md-ui-page md-ui-page--stack">
    <x-md.page-hero
        eyebrow="Справочник"
        icon="book"
        title="База знаний"
        lede="Руководства по настройке дома, автоматизации и разработке. Найдите ответ по теме или ключевым словам." />

    @yoyo('knowledge-catalog', ['search' => $search, 'category' => $category])
</div>
