<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome module</title>
</head>
<body>
    <main>
        <h1>Welcome module</h1>
        <p>Модуль <code>{{ $module }}</code> подключён из <code>module.json</code>.</p>
        <p>Ядро не знает про этот модуль: маршруты и Blade-namespace зарегистрированы динамически.</p>
        <p><a href="/module/welcome/hello/MyDoMo">Проверить параметр маршрута</a></p>
    </main>
</body>
</html>
