@props([
    'id' => null,
    'title' => '',
    'footer' => null,
    'actions' => null,
    'size' => null,
])

@if ($id !== null)
    @php
        $size = in_array($size, ['sm', 'lg', 'xl'], true) ? ' modal-' . $size : '';
    @endphp

    <div x-data="mdDialog(@js($id))">
        <div class="modal-backdrop fade"
             :class="{'show': $store.mdModal.open === @js($id)}"
             :style="$store.mdModal.open === @js($id) ? 'display: block' : 'display: none'"
             @click="$store.mdModal.close()"
             aria-hidden="true"></div>

        <div {{ $attributes->class(['modal', 'fade', 'md-modal']) }}
             id="{{ $id }}"
             x-ref="dialog"
             tabindex="-1"
             role="dialog"
             aria-modal="true"
             aria-labelledby="{{ $id }}-title"
             :aria-hidden="$store.mdModal.open === @js($id) ? 'false' : 'true'"
             :class="{'show': $store.mdModal.open === @js($id)}"
             :style="$store.mdModal.open === @js($id) ? 'display: block' : 'display: none'"
             @keydown.escape.window="$store.mdModal.close()"
             @keydown.tab="trap($event)"
             @click.self="$store.mdModal.close()">
            <div class="modal-dialog modal-dialog-centered{{ $size }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="{{ $id }}-title">{{ $title }}</h2>
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
