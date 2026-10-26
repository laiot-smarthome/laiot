<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="message" /> core · say()</div>
            <h1 class="md-ui-hero__title">Shoutbox</h1>
            <p class="md-ui-hero__lede">Сообщения core-модуля: ModuleDb, Blade и alpha-layout.</p>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Новое сообщение" subtitle="До 255 символов">
        <form method="post" action="/module/shoutbox" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.textarea name="message" label="Сообщение" rows="3" required />
            <x-md.button type="submit" icon="send">Отправить</x-md.button>
        </form>
    </x-md.card>

    <x-md.activity-feed title="Последние сообщения" :items="array_map(static fn (array $row): array => [
        'title' => 'Сообщение #' . $row['id'],
        'text' => $row['message'],
        'time' => substr($row['created_at'], 11, 5),
        'date' => substr($row['created_at'], 0, 10),
        'tone' => 'primary',
        'meta' => [$row['source']],
        'actions' => '<form method="post" action="/module/shoutbox/' . $row['id'] . '/delete"><input type="hidden" name="_token" value="' . e($csrf) . '"><button class="md-btn md-btn--danger md-btn--sm" type="submit">Удалить</button></form>',
    ], $messages)" />
</div>
