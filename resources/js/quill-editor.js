import Quill from 'quill';

window.Quill = Quill;

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('.quill-editor-wrapper').forEach(wrapper => {
            wrapper.querySelectorAll('.ql-toolbar, .ql-clipboard, .ql-tooltip').forEach(el => el.remove());
            const editorEl = wrapper.querySelector('[x-ref="editor"]') || wrapper.querySelector('.ql-container');
            if (editorEl) {
                editorEl.className = '';
                editorEl.innerHTML = '';
            }
        });
        if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch('wysiwyg-reload');
        }
    }
});

function disableThirdPartyExtensions(el) {
    if (!el) return;
    el.setAttribute('data-gramm', 'false');
    el.setAttribute('data-gramm_editor', 'false');
    el.setAttribute('data-enable-grammarly', 'false');
    el.setAttribute('spellcheck', 'false');
    el.setAttribute('autocorrect', 'off');
    el.setAttribute('autocapitalize', 'off');
}

function enforceTypeButton(toolbarContainer) {
    if (!toolbarContainer) return;
    toolbarContainer.querySelectorAll('button').forEach(b => b.setAttribute('type', 'button'));
    new MutationObserver(() => {
        toolbarContainer.querySelectorAll('button:not([type])').forEach(b => b.setAttribute('type', 'button'));
    }).observe(toolbarContainer, { childList: true, subtree: true });
}

function resetEditorElement(editorEl) {
    if (!editorEl) return;
    const wrapper = editorEl.closest('.quill-editor-wrapper');
    if (wrapper) {
        wrapper.querySelectorAll('.ql-toolbar').forEach(t => t.remove());
        wrapper.querySelectorAll('.ql-clipboard').forEach(c => c.remove());
        wrapper.querySelectorAll('.ql-tooltip').forEach(t => t.remove());
    }
    if (editorEl.classList.contains('ql-container')) {
        editorEl.className = '';
    }
    const inner = editorEl.querySelector('.ql-editor');
    if (inner) {
        editorEl.innerHTML = '';
    }
}

window.QuillEditor = {
    init(ctx, config) {
        const editorEl = ctx.$refs.editor;
        resetEditorElement(editorEl);
        disableThirdPartyExtensions(editorEl);

        const quill = new Quill(editorEl, {
            theme: 'snow',
            placeholder: config.placeholder || '',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block'],
                        [{ align: [] }],
                        [{ color: [] }, { background: [] }],
                        ['link', 'image'],
                        ['clean'],
                    ],
                    handlers: {
                        image: function () {
                            QuillEditor.selectImage(ctx, config);
                        },
                    },
                },
            },
        });

        ctx.quill = quill;
        disableThirdPartyExtensions(quill.root);

        const toolbarModule = quill.getModule('toolbar');
        if (toolbarModule && toolbarModule.container) {
            enforceTypeButton(toolbarModule.container);
        }

        const paintHtml = (html) => {
            ctx.updatingFromWatch = true;
            try {
                if (html) {
                    const delta = quill.clipboard.convert(html);
                    quill.setContents(delta, 'silent');
                } else {
                    quill.setText('', 'silent');
                }
            } catch (e) {
                quill.root.innerHTML = html || '<p><br></p>';
            }
            quill.history.clear();
            ctx.$nextTick(() => { ctx.updatingFromWatch = false; });
        };

        if (ctx.value) {
            paintHtml(ctx.value);
        }

        quill.on('text-change', () => {
            if (ctx.updatingFromWatch) return;
            const html = quill.root.innerHTML;
            ctx.value = html === '<p><br></p>' ? '' : html;
        });

        ctx.$watch('value', (newVal) => {
            if (ctx.updatingFromWatch || !ctx.quill) return;
            const currentHtml = ctx.quill.root.innerHTML;
            const targetHtml = newVal || '<p><br></p>';
            if (currentHtml === targetHtml) return;
            paintHtml(newVal || '');
        });

        if (window.Livewire) {
            Livewire.on('wysiwyg-reload', () => {
                ctx.$nextTick(() => {
                    if (!ctx.quill) return;
                    paintHtml(ctx.value || '');
                });
            });
        }
    },

    selectImage(ctx, config) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = () => {
            const file = input.files && input.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                window.Flux?.toast({ text: config.t?.onlyImages || 'Only image files are allowed.', variant: 'danger' });
                return;
            }
            QuillEditor.uploadImage(ctx, file, config);
        };
        input.click();
    },

    uploadImage(ctx, file, config) {
        const formData = new FormData();
        formData.append('file', file);

        window.axios.post(config.uploadUrl, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            .then(response => {
                let range = ctx.quill.getSelection(true);
                if (!range) range = { index: ctx.quill.getLength() - 1, length: 0 };
                ctx.quill.insertEmbed(range.index, 'image', response.data.url, 'user');
                ctx.quill.setSelection(range.index + 1, 0, 'silent');

                ctx.$nextTick(() => {
                    const selector = `img[src='${response.data.url}']:not([data-wysiwyg-media-id])`;
                    const imgs = ctx.quill.root.querySelectorAll(selector);
                    imgs.forEach(img => img.setAttribute('data-wysiwyg-media-id', response.data.wysiwygMediaId));
                    ctx.value = ctx.quill.root.innerHTML;
                });
            })
            .catch(error => {
                console.error('Upload failed:', error?.response?.status, error?.response?.data || error.message);
                window.Flux?.toast({ text: config.t?.uploadFailed || 'Image upload failed.', variant: 'danger' });
            });
    },
};
