@props([
    'items' => [],
    'active' => '',
    'label' => null,
])

{{--
    Вкладки раздела — обычные ссылки: состояние живёт в URL, страницу
    рендерит сервер. Bootstrap-JS для вкладок не нужен и не подключается.
--}}
<nav {{ $attributes->class(['md-tabs']) }} @if ($label !== null) aria-label="{{ $label }}" @endif>
    @foreach ($items as $item)
        @php
            $id = (string)($item['id'] ?? '');
            $isActive = $id === (string)$active;
        @endphp
        <a class="nav-link @if ($isActive) active @endif"
           href="{{ $item['href'] ?? '#' }}"
           @if ($isActive) aria-current="page" @endif>
            @if (!empty($item['icon']))<x-md.icon :name="$item['icon']" />@endif
            <span>{{ $item['label'] ?? $id }}</span>
            @if (isset($item['count']) && (int)$item['count'] > 0)
                <span class="md-badge">{{ (int)$item['count'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
