@props(['label' => '', 'placeholder' => '', 'deferDelete' => false])
@php
    $model = $attributes->wire('model')->value();
    $id = 'wysiwyg-' . Str::random(8);
@endphp
<flux:field>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif
    <div wire:ignore x-data="{
        value: @entangle($attributes->wire('model')),
        mediaMap: new Map(),
        updatingFromWatch: false,

        init() {
            const trixEl = this.$refs.editor;
            const hiddenInput = this.$refs.input;

            if (this.value) {
                hiddenInput.value = this.value;
            }

            const loadContent = () => {
                if (this.value && trixEl.editor) {
                    trixEl.editor.loadHTML(this.value);
                }
            };

            // Handle fast refresh where trix may already be initialized
            if (trixEl.editor) {
                loadContent();
            }
            trixEl.addEventListener('trix-initialize', loadContent);

            trixEl.addEventListener('trix-change', () => {
                if (this.updatingFromWatch) return;
                this.value = trixEl.value;
            });

            // Watch for external value changes (e.g. locale switch) and reload editor
            this.$watch('value', (newVal) => {
                if (this.updatingFromWatch || !trixEl.editor) return;
                if (trixEl.value !== (newVal || '')) {
                    this.updatingFromWatch = true;
                    trixEl.editor.loadHTML(newVal || '');
                    this.$nextTick(() => { this.updatingFromWatch = false; });
                }
            });

            // Force-reload when content is set programmatically (e.g. AI generation)
            Livewire.on('wysiwyg-reload', () => {
                this.$nextTick(() => {
                    if (trixEl.editor && this.value) {
                        this.updatingFromWatch = true;
                        trixEl.editor.loadHTML(this.value);
                        this.$nextTick(() => { this.updatingFromWatch = false; });
                    }
                });
            });

            trixEl.addEventListener('trix-attachment-add', (e) => {
                const attachment = e.attachment;
                if (!attachment.file) return;

                if (!attachment.file.type.startsWith('image/')) {
                    attachment.remove();
                    Flux.toast({ text: '{{ __('Only image files are allowed.') }}', variant: 'danger' });
                    return;
                }

                this.uploadFile(attachment);
            });

            trixEl.addEventListener('trix-attachment-remove', (e) => {
                // Check mediaMap first (files uploaded in this session)
                let wysiwygMediaId = this.mediaMap.get(e.attachment);

                // Fall back to attachment attributes (files loaded from existing HTML)
                if (!wysiwygMediaId) {
                    wysiwygMediaId = e.attachment.getAttributes().wysiwygMediaId;
                }

                if (!wysiwygMediaId) return;

                this.mediaMap.delete(e.attachment);

                @if(!$deferDelete)
                axios.delete(
                    '{{ route('wysiwyg.destroy', '__ID__') }}'.replace('__ID__', wysiwygMediaId)
                );
                @endif
            });
        },

        uploadFile(attachment) {
            const formData = new FormData();
            formData.append('file', attachment.file);

            axios.post('{{ route('wysiwyg.upload') }}', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                })
                .then(response => {
                    attachment.setAttributes({
                        url: response.data.url,
                        href: response.data.url,
                        wysiwygMediaId: response.data.wysiwygMediaId,
                    });

                    this.mediaMap.set(attachment, response.data.wysiwygMediaId);
                })
                .catch(error => {
                    console.error('Upload failed:', error?.response?.status, error?.response?.data || error.message);
                });
        }
    }">
        <input x-ref="input" id="{{ $id }}" type="hidden">
        <trix-editor x-ref="editor" input="{{ $id }}" placeholder="{{ $placeholder }}"
            class="trix-editor-styled"></trix-editor>
    </div>
    @if ($model)
        <flux:error :name="$model" />
    @endif
</flux:field>
