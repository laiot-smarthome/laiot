@props([
    'id' => null,
    'title' => '',
    'footer' => null,
    'actions' => null,
    'size' => 'md',
])

@if ($id !== null)
    @php
        // Ширина задаётся вариантом компонента и не зависит от содержимого.
        $size = in_array($size, ['sm', 'md', 'lg', 'xl'], true) ? $size : 'md';
        $dialogSize = $size === 'md' ? '' : ' modal-' . $size;
    @endphp

    <div x-data="mdDialog(@js($id))">
        <div class="modal-backdrop fade"
             :class="{'show': $store.mdModal.open === @js($id)}"
             :style="$store.mdModal.open === @js($id) ? 'display: block' : 'display: none'"
             @click="$store.mdModal.close()"
             aria-hidden="true"></div>

        <div {{ $attributes->class(['modal', 'fade', 'md-modal', 'md-modal--' . $size]) }}
             id="{{ $id }}"
             x-ref="dialog"
             tabindex="-1"
             role="dialog"
             aria-modal="true"
             aria-labelledby="{{ $id }}-title"
             :aria-hidden="$store.mdModal.open === @js($id) ? 'false' : 'true'"
             :class="{'show': $store.mdModal.open === @js($id)}"
             :style="$store.mdModal.open === @js($id) ? 'display: block' : 'display: none'"
             x-effect="if ($store.mdModal.open === @js($id)) $nextTick(() => $dispatch('md:panel-opened'))"
             @keydown.escape.window="$store.mdModal.close()"
             @keydown.tab="trap($event)"
             @click.self="$store.mdModal.close()">
            <div class="modal-dialog modal-dialog-centered{{ $dialogSize }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title" id="{{ $id }}-title">{{ $title }}</h2>
                        <button type="button" class="btn-close" @click="$store.mdModal.close()" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">{{ $slot }}</div>
                    @if ($footer !== null || trim((string)($actions ?? '')) !== '')
                        <div class="modal-footer">{!! $footer !!}{{ $actions ?? '' }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
