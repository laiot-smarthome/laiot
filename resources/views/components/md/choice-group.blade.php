@props(['name', 'options' => [], 'selected' => null, 'label' => null, 'required' => false, 'disabled' => false])

<fieldset {{ $attributes->class(['md-choice-group']) }} @disabled($disabled)>
    @if ($label !== null)<legend class="md-field__label">{{ $label }}</legend>@endif
    @foreach ($options as $key => $option)
        @php
            $value = is_array($option) ? (string) ($option['value'] ?? $key) : (string) $key;
            $title = is_array($option) ? ($option['label'] ?? $value) : $option;
        @endphp
        <label class="md-choice">
            <input class="md-choice__input" type="radio" name="{{ $name }}" value="{{ $value }}"
                   @checked((string) $selected === $value) @required($required) @disabled(is_array($option) && !empty($option['disabled']))>
            <span class="md-choice__surface">
                @if (is_array($option) && !empty($option['icon']))<span class="md-choice__icon"><x-md.icon :name="$option['icon']" /></span>@endif
                <span class="md-choice__body"><span class="md-choice__title">{{ $title }}</span>
                    @if (is_array($option) && !empty($option['description']))<span class="md-choice__desc">{{ $option['description'] }}</span>@endif
                </span>
                <span class="md-choice__marker" aria-hidden="true"></span>
            </span>
        </label>
    @endforeach
</fieldset>
