<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $csrf }}">
    <title>Yoyo + Blade</title>
    @yoyo_scripts
</head>
<body>
    <main>
        <h1>Yoyo + Blade</h1>
        <p>Серверный компонент без отдельного JavaScript.</p>
        @yoyo('counter')
    </main>
    <script>
        document.body.addEventListener('htmx:configRequest', function (event) {
            event.detail.headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').content;
        });
    </script>
</body>
</html>
