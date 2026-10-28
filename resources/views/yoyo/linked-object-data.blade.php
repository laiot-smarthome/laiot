<div hidden data-md-linked-data hx-sync="this:replace" x-data x-init="$dispatch('md-linked-data', @js($payload))">
    <input type="hidden" name="query" value="{{ $query }}">
    <input type="hidden" name="object" value="{{ $object }}">
    <input type="hidden" name="sequence" value="{{ $sequence }}">
    <button type="button" data-md-linked-load yoyo:get="render">Обновить варианты</button>
</div>
