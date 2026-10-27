@props(['label' => 'Последняя активность', 'value' => '—'])
<span {{ $attributes->class(['md-last-seen']) }}><x-md.icon name="clock" /><span>{{ $label }}: {{ $value }}</span></span>
