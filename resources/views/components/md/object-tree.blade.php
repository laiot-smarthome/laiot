@props(['items' => [], 'label' => 'Дерево объектов', 'depth' => 0])
@if ($depth === 0 && $items === [])
    <x-md.empty-state title="Нет объектов" text="Объекты появятся после добавления." compact />
@elseif ($depth < 24)
    <ul {{ $attributes->class(['md-object-tree', 'md-object-tree--nested' => $depth > 0]) }} @if ($depth === 0) aria-label="{{ $label }}" @endif>
        @foreach ($items as $item)
            @php
                $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                $href = $item['href'] ?? null;
                $href = is_string($href) && (str_starts_with($href, '#') || (str_starts_with($href, '/') && !str_starts_with($href, '//'))) ? $href : null;
            @endphp
            <li class="md-object-tree__item">
                @if ($children !== [])
                    <details @if (!empty($item['open'])) open @endif><summary><x-md.icon name="chevron-right" class="md-object-tree__chevron" /><x-md.icon :name="$item['icon'] ?? 'folder'" /><span>{{ $item['label'] ?? '' }}</span></summary>
                        <x-md.object-tree :items="$children" :depth="$depth + 1" />
                    </details>
                @elseif ($href !== null)
                    <a class="md-object-tree__leaf" href="{{ $href }}"><x-md.icon :name="$item['icon'] ?? 'box'" /><span>{{ $item['label'] ?? '' }}</span></a>
                @else
                    <span class="md-object-tree__leaf"><x-md.icon :name="$item['icon'] ?? 'box'" /><span>{{ $item['label'] ?? '' }}</span></span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
