<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome, {{ $name }}</title>
</head>
<body>
    <main>
        <h1>Привет, {{ $name }}!</h1>
        <p>Параметр маршрута пришёл в контроллер модуля.</p>
        <p><a href="/module/welcome">Назад</a></p>
    </main>
</body>
</html>
