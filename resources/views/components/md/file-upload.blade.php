@props(['name', 'id' => null, 'label' => 'Файлы', 'accept' => null, 'multiple' => false, 'disabled' => false, 'hint' => null])
@php($id = $id ?? 'md-upload-' . substr(md5($name), 0, 8))
<div class="md-field" x-data="mdFileUpload()">
    <span class="md-field__label">{{ $label }}</span>
    <label class="md-upload" :class="{'is-dragging': dragging}" @dragover.prevent="if (!$refs.input.disabled) dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)">
        <input x-ref="input" id="{{ $id }}" type="file" name="{{ $name }}{{ $multiple && !str_ends_with($name, '[]') ? '[]' : '' }}" @if ($accept !== null) accept="{{ $accept }}" @endif @if ($hint !== null) aria-describedby="{{ $id }}-help" @endif @if ($multiple) multiple @endif @disabled($disabled) @change="update()" {{ $attributes }}>
        <span class="md-upload__icon"><x-md.icon name="cloud-upload" /></span>
        <span class="md-upload__title">Перетащите файлы или выберите</span>
        @if ($hint !== null)<span id="{{ $id }}-help" class="md-upload__hint">{{ $hint }}</span>@endif
    </label>
    <ul class="md-upload__list" x-cloak x-show="files.length">
        <template x-for="(file, index) in files" :key="index"><li class="md-upload__item"><x-md.icon name="file" /><span class="md-upload__name" x-text="file.name"></span><span class="md-upload__size" x-text="formatSize(file.size)"></span><button type="button" class="md-icon-btn md-icon-btn--sm" @click="remove(index)" :aria-label="'Удалить ' + file.name"><x-md.icon name="x" /></button></li></template>
    </ul>
</div>
