@props(['model', 'label' => 'Slug', 'placeholder' => 'maxi-baxi-2000', 'modifier' => ''])

{{-- The wire:model directive (with its optional modifier, e.g. `.blur`) is applied
     via the attribute bag. A dynamic attribute *name* written inline as
     `wire:model{{ $modifier }}` is silently dropped by Blade's component-tag
     parser, so it must be merged in instead. --}}
<flux:input {{ $attributes->merge(['wire:model'.$modifier => $model]) }} label="{{ $label }}" placeholder="{{ $placeholder }}"
    x-on:keydown.space.prevent="
        let s = $el.selectionStart;
        let v = $el.value;
        $el.value = v.slice(0, s) + '-' + v.slice($el.selectionEnd);
        $el.setSelectionRange(s + 1, s + 1);
        $el.dispatchEvent(new Event('input', { bubbles: true }));
    " x-on:input="
        let start = $el.selectionStart;
        let old = $el.value;

        let sanitized = old.toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '')
            .replace(/-{2,}/g, '-');

        if (sanitized !== old) {
            let diff = old.length - sanitized.length;

            $el.value = sanitized;

            $el.setSelectionRange(
                Math.max(0, start - diff),
                Math.max(0, start - diff)
            );
        }
    " />