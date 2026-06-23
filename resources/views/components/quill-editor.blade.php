@props(['label' => '', 'placeholder' => '', 'deferDelete' => false])
@php
    $model = $attributes->wire('model')->value();
    $config = [
        'placeholder' => $placeholder,
        'uploadUrl' => route('wysiwyg.upload'),
        't' => [
            'onlyImages' => __('Only image files are allowed.'),
            'uploadFailed' => __('Image upload failed.'),
        ],
    ];
@endphp
<flux:field>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif
    <div wire:ignore x-data="{
        value: @entangle($attributes->wire('model')),
        quill: null,
        updatingFromWatch: false,
        init() { window.QuillEditor.init(this, @js($config)); }
    }" class="quill-editor-wrapper">
        <div x-ref="editor" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" spellcheck="false"></div>
    </div>
    @if ($model)
        <flux:error :name="$model" />
    @endif
</flux:field>
