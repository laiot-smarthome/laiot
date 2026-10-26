<div class="md-ui-page md-ui-page--stack">
    <section class="md-ui-hero">
        <div>
            <div class="md-ui-hero__eyebrow"><x-md.icon name="user-circle" /> аккаунт</div>
            <h1 class="md-ui-hero__title">Профиль</h1>
            <p class="md-ui-hero__lede">{{ $user['username'] ?? '' }}</p>
        </div>
    </section>

    @if ($error !== null)
        <x-md.alert type="danger" :text="$error" />
    @endif

    <x-md.card title="Данные">
        <form method="post" action="/admin/profile" class="md-form">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <x-md.input name="name" label="Имя" :value="$name" required />
            <x-md.input name="password" type="password" label="Новый пароль" hint="Оставьте пустым, чтобы не менять" />
            <x-md.button type="submit" icon="check">Сохранить</x-md.button>
        </form>
    </x-md.card>
</div>
