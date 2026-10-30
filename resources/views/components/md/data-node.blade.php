@if (isset($node['children']))
    @if ($depth === 0)
        <div class="md-data-group"><div class="md-data-type">{{ $node['type'] }}<span>({{ $node['count'] }})</span> [</div>
            <div class="md-data-children">
                @foreach ($node['children'] as $child)
                    @include('components.md.data-node', ['node' => $child['node'], 'key' => $child['key'], 'depth' => $depth + 1])
                @endforeach
            </div><span class="md-data-type">]</span>
        </div>
    @else
        <details class="md-data-group" data-md-inspect><summary><span class="md-data-key">{{ $key }}</span><span class="md-data-arrow"> =&gt; </span><span class="md-data-type">{{ $node['type'] }}({{ $node['count'] }})</span></summary>
            <div class="md-data-children">
                @foreach ($node['children'] as $child)
                    @include('components.md.data-node', ['node' => $child['node'], 'key' => $child['key'], 'depth' => $depth + 1])
                @endforeach
            </div>
        </details>
    @endif
    @if ($node['truncated'])<span class="md-table__muted">… снимок ограничен по размеру</span>@endif
@else
    <div class="md-data-line">
        @if ($key !== null)<span class="md-data-key">{{ $key }}</span><span class="md-data-arrow"> =&gt; </span>@endif
        <span class="md-data-scalar md-data-scalar--{{ in_array($node['type'], ['string', 'int', 'float', 'bool', 'null'], true) ? $node['type'] : 'other' }}">{{ var_export($node['value'], true) }}</span><span class="md-data-hint">{{ $node['type'] }}</span>
        @if ($node['truncated'] ?? false)<span class="md-table__muted">… сокращено</span>@endif
    </div>
@endif
