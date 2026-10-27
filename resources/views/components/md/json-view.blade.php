@props(['title' => 'JSON', 'data' => null])
@php($json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
@if ($json === false)
    <x-md.alert type="danger" title="Не удалось показать JSON" text="Данные не удалось преобразовать в JSON." />
@else
    <x-md.code-sample :title="$title" :code="$json" language="json" {{ $attributes }} />
@endif
