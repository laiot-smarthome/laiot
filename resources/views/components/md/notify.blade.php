@props(['type' => 'info', 'title' => '', 'text' => '', 'duration' => null])
@php
    $tone = in_array($type, ['info', 'success', 'warning', 'danger'], true) ? $type : 'info';
    $timeout = $duration === null ? (in_array($tone, ['warning', 'danger'], true) ? 0 : 5000) : max(0, (int)$duration);
@endphp
<span hidden data-md-notify data-tone="{{ $tone }}" data-title="{{ $title }}" data-text="{{ $text }}" data-duration="{{ $timeout }}"
      x-data x-init="$store.mdToasts.push({tone: $el.dataset.tone, title: $el.dataset.title, text: $el.dataset.text, duration: Number($el.dataset.duration)})"></span>
