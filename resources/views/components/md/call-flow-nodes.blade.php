@props(['nodes' => []])
<ol class="md-kit-flow">@foreach ($nodes as $node)
    <li><x-md.entity :name="$node['name'] ?? ''" :type="$node['type'] ?? 'object'" />
        @if (!empty($node['status']))<x-md.badge tone="info">{{ $node['status'] }}</x-md.badge>@endif
        @if (!empty($node['detail']))<p>{{ $node['detail'] }}</p>@endif
        @if (!empty($node['children']))<x-md.call-flow-nodes :nodes="$node['children']" />@endif
        @foreach ($node['branches'] ?? [] as $branch)
            <div class="md-kit-flow__branch"><strong>{{ $branch['label'] ?? 'Ветка' }}</strong>
                @if (array_key_exists('taken', $branch))<x-md.badge :tone="$branch['taken'] ? 'success' : 'neutral'">{{ $branch['taken'] ? 'Выполнена' : 'Пропущена' }}</x-md.badge>@endif
                @if (!empty($branch['nodes']))<x-md.call-flow-nodes :nodes="$branch['nodes']" />@endif
            </div>
        @endforeach
    </li>
@endforeach</ol>
