@props(['label', 'state' => 'idle', 'detail' => null])
<div {{ $attributes->class(['md-connection']) }}><span><x-md.icon name="wifi" /> {{ $label }}</span><x-md.status :state="$state" />@if ($detail !== null)<small>{{ $detail }}</small>@endif</div>
