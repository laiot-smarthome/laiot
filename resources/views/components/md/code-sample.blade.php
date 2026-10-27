@props(['title' => null, 'code' => ''])
<div {{ $attributes->class(['md-kit-code-sample']) }} x-data="{copied: false, async copy() { try { await navigator.clipboard.writeText(this.$refs.code.textContent); this.copied = true; } catch { this.copied = false; } }}">
    <div class="md-kit-code-sample__head">@if ($title !== null)<strong>{{ $title }}</strong>@endif<button type="button" class="md-btn md-btn--secondary md-btn--sm" @click="copy()" x-text="copied ? 'Скопировано' : 'Копировать'"></button></div>
    <pre><code x-ref="code">{{ $code }}</code></pre>
</div>
