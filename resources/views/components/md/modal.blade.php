@props([
    'id' => null,
    'title' => '',
    'footer' => null,
    'size' => null,
])

@if ($id !== null)
    @php
        $size = in_array($size, ['sm', 'lg', 'xl'], true) ? ' modal-' . $size : '';
    @endphp

    <div {{ $attributes->class(['modal', 'fade', 'md-modal']) }}
         id="{{ $id }}"
         tabindex="-1"
         aria-labelledby="{{ $id }}-title"
         aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered{{ $size }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="{{ $id }}-title">{{ $title }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">{{ $slot }}</div>
                @if ($footer !== null)
                    <div class="modal-footer">{!! $footer !!}</div>
                @endif
            </div>
        </div>
    </div>
@endif
