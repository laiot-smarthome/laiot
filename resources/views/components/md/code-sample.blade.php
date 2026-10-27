@props(['title' => null, 'code' => '', 'language' => 'text'])
<div {{ $attributes->class(['md-code-sample']) }} x-data="{copied: false, async copy() { try { await navigator.clipboard.writeText(this.$refs.code.textContent); this.copied = true; } catch { this.copied = false; } }}">
    <div class="md-code-sample__head"><div class="md-code-sample__titles">@if ($title !== null)<strong class="md-code-sample__title">{{ $title }}</strong>@endif</div><div class="md-code-sample__actions"><span class="md-code-sample__lang">{{ strtoupper($language) }}</span><button type="button" class="md-code-sample__copy" @click="copy()" x-text="copied ? 'Скопировано' : 'Копировать'"></button></div></div>
    <div class="md-code-sample__scroll"><pre class="md-code-sample__pre"><code class="md-code-sample__code" x-ref="code" data-code-sample data-language="{{ $language }}">{{ $code }}</code></pre></div>
</div>
