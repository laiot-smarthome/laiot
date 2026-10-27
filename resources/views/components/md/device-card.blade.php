@props(['name', 'room' => null, 'type' => null, 'icon' => 'device-desktop', 'state' => 'online', 'value' => null, 'unit' => null, 'updated' => null, 'connection' => null, 'href' => null])

<article {{ $attributes->class(['md-device', 'md-device--' . $state]) }}>
    <header class="md-device__head">
        <span class="md-device__icon md-device__icon--primary"><x-md.icon :name="$icon" /></span>
        <div class="md-device__identity">
            <h3 class="md-device__name">@if ($href !== null)<a href="{{ $href }}">{{ $name }}</a>@else{{ $name }}@endif</h3>
            <div class="md-device__meta">{{ implode(' · ', array_filter([$room, $type])) }}</div>
        </div>
        <x-md.status :state="$state" />
    </header>
    <div class="md-device__body">
        @if ($value !== null)<div class="md-device__value">{{ $value }}@if ($unit !== null)<small>{{ $unit }}</small>@endif</div>@endif
        <div class="md-device__actions">{{ $slot }}</div>
    </div>
    @if ($updated !== null || $connection !== null)
        <footer class="md-device__footer"><span class="md-device__updated">@if ($updated !== null)<x-md.icon name="clock" />{{ $updated }}@endif</span><span class="md-device__link">{{ $connection }}</span></footer>
    @endif
</article>
