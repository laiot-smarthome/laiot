<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход в MyDoMo</title>
</head>
<body>
    <main>
        <h1>Вход в MyDoMo</h1>
        @if ($error)
            <p role="alert">{{ $error }}</p>
        @endif
        <form method="post" action="/login">
            <input type="hidden" name="_token" value="{{ $csrf }}">
            <p>
                <label for="username">Логин</label>
                <input id="username" name="username" type="text" autocomplete="username" required>
            </p>
            <p>
                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </p>
            <button type="submit">Войти</button>
        </form>
    </main>
</body>
</html>
