@props(['name', 'id' => null, 'label' => 'Файлы', 'accept' => null, 'multiple' => false, 'disabled' => false, 'hint' => null])
@php($id = $id ?? 'md-upload-' . substr(md5($name), 0, 8))
<div class="md-field" x-data="{dragging: false, names: [], update() { this.names = Array.from(this.$refs.input.files, file => file.name); }, drop(event) { if (this.$refs.input.disabled) return; this.dragging = false; const files = event.dataTransfer.files; if (!files.length) return; if (this.$refs.input.multiple) this.$refs.input.files = files; else { const transfer = new DataTransfer(); transfer.items.add(files[0]); this.$refs.input.files = transfer.files; } this.$refs.input.dispatchEvent(new Event('change', {bubbles: true})); }}">
    <label class="md-field__label" for="{{ $id }}">{{ $label }}</label>
    <div class="md-kit-upload" :class="{'is-dragging': dragging}" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)">
        <x-md.icon name="upload" />
        <p>Перетащите файлы или выберите их на устройстве.</p>
        <input x-ref="input" id="{{ $id }}" type="file" name="{{ $name }}{{ $multiple && !str_ends_with($name, '[]') ? '[]' : '' }}" @if ($accept !== null) accept="{{ $accept }}" @endif @if ($hint !== null) aria-describedby="{{ $id }}-help" @endif @if ($multiple) multiple @endif @disabled($disabled) @change="update()" {{ $attributes }}>
        <ul x-cloak x-show="names.length"><template x-for="(file, index) in names" :key="index"><li x-text="file"></li></template></ul>
    </div>
    @if ($hint !== null)<div id="{{ $id }}-help" class="md-field__hint">{{ $hint }}</div>@endif
</div>
