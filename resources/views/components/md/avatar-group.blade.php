@props(['items' => [], 'size' => 'md'])
<div {{ $attributes->class(['md-avatar-group']) }} aria-label="Участники">
    @foreach ($items as $item)<x-md.avatar :name="is_array($item) ? ($item['name'] ?? '') : $item" :src="is_array($item) ? ($item['src'] ?? null) : null" :size="$size" />@endforeach
</div>
