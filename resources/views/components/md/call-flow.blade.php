@props(['title' => 'Схема вызова', 'nodes' => []])
<x-md.card :title="$title" icon="route" {{ $attributes }}>
    @if ($nodes === [])<x-md.empty-state title="Нет данных о вызове" compact />@else
        <ol class="md-kit-flow">@foreach ($nodes as $node)
            <li><x-md.entity :name="$node['name'] ?? ''" :type="$node['type'] ?? 'object'" /> @if (!empty($node['status']))<x-md.badge tone="info">{{ $node['status'] }}</x-md.badge>@endif @if (!empty($node['detail']))<p>{{ $node['detail'] }}</p>@endif
                @if (!empty($node['children']))<x-md.call-flow-nodes :nodes="$node['children']" />@endif
            </li>
        @endforeach</ol>
    @endif
</x-md.card>
