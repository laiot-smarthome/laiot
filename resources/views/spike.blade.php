<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>Blade работает.</p>
        <ul>
            @foreach ($checks as $name => $status)
                <li>{{ $name }}: {{ $status }}</li>
            @endforeach
        </ul>
    </main>
</body>
</html>
