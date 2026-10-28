@props(['title' => 'Пока ничего нового', 'text' => 'Новые уведомления появятся здесь.', 'pose' => 'sleep'])

<div {{ $attributes->class(['md-mascot-empty']) }}>
    <div class="md-mascot-empty__scene" aria-hidden="true">
        <x-md.mascot :pose="$pose" />
        <span class="md-mascot-empty__badge"><x-md.icon name="check" /></span>
    </div>
    <div class="md-mascot-empty__copy">
        <h3>{{ $title }}</h3>
        <p>{{ $text }}</p>
    </div>
</div>
