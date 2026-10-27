@props([
    'id' => null,
    'eyebrow' => null,
    'title' => '',
    'subtitle' => null,
    'footer' => null,
    'overlay' => true,
    'width' => null,
    'closeLabel' => 'Закрыть',
    'bodyId' => null,
    'footerId' => null,
    'eyebrowId' => null,
    'subtitleId' => null,
    'titleId' => null,
])

@php
    $id = $id ?? 'md-drawer-' . substr(md5($title . '|' . ($subtitle ?? '')), 0, 8);
    $titleId = $titleId ?? $id . '-title';
@endphp

<div x-data="mdDialog(@js($id), 'mdDrawer')">
    @if ($overlay)
        <div class="md-admin-drawer-backdrop"
             :class="{'is-open': $store.mdDrawer.open === @js($id)}"
             x-cloak
             x-show="$store.mdDrawer.open === @js($id)"
             @click="$store.mdDrawer.close()"></div>
    @endif

    <aside {{ $attributes->class(['md-admin-drawer', 'md-drawer']) }}
           id="{{ $id }}"
           x-ref="dialog"
           aria-labelledby="{{ $titleId }}"
           :aria-hidden="$store.mdDrawer.open === @js($id) ? 'false' : 'true'"
           :class="{'is-open': $store.mdDrawer.open === @js($id)}"
           x-show="$store.mdDrawer.open === @js($id)"
           x-effect="if ($store.mdDrawer.open === @js($id)) $nextTick(() => $dispatch('md:panel-opened'))"
           x-cloak
           tabindex="-1"
           @keydown.escape.window="$store.mdDrawer.close()"
           @if ($overlay) @keydown.tab="trap($event)" @endif
           @if ($overlay) role="dialog" aria-modal="true" @endif
           @if ($width !== null) style="--md-admin-drawer-width: {{ $width }}" @endif>
        <div class="md-admin-drawer__header md-drawer__header">
            <div>
                @if ($eyebrow !== null)
                    <div class="md-admin-drawer__eyebrow md-drawer__eyebrow" @if ($eyebrowId !== null) id="{{ $eyebrowId }}" @endif>{{ $eyebrow }}</div>
                @endif
                <h2 class="md-drawer__title" id="{{ $titleId }}">{{ $title }}</h2>
                @if ($subtitle !== null)
                    <div class="md-admin-drawer__subtitle md-drawer__subtitle" @if ($subtitleId !== null) id="{{ $subtitleId }}" @endif>{{ $subtitle }}</div>
                @endif
            </div>
            <button type="button"
                    class="md-icon-btn md-admin-drawer__close"
                    @click="$store.mdDrawer.close()"
                    aria-label="{{ $closeLabel }}">
                <x-md.icon name="x" />
            </button>
        </div>
        <div class="md-admin-drawer__body md-drawer__body" @if ($bodyId !== null) id="{{ $bodyId }}" @endif>{{ $slot }}</div>
        @if ($footer !== null)
            <div class="md-admin-drawer__footer md-drawer__footer" @if ($footerId !== null) id="{{ $footerId }}" @endif>{!! $footer !!}</div>
        @endif
    </aside>
</div>
