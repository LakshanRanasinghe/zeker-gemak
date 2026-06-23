@script
    <script>
        const ProgressOverlay = {
            overlay: null,
            cards: {},

            init() {
                if (this.overlay) return;

                this.overlay = document.createElement('div');
                this.overlay.className =
                    'fixed top-6 left-6 bg-white shadow-2xl rounded-xl p-4 max-w-sm z-50 border border-gray-200';
                this.overlay.style.minWidth = '240px';
                this.overlay.innerHTML = `
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                        <strong class="text-sm">Uploads</strong>
                    </div>

                    <div id="__progress_cards" style="
                        max-height: calc(90vh - 56px); 
                        overflow-y: auto; 
                        margin-right: -8px;
                        padding-right: 4px;" 
                        class="space-y-2">
                    </div>
                `;
                document.body.appendChild(this.overlay);

                if (!document.getElementById('__progress_overlay_scroll_style')) {
                    const style = document.createElement('style');
                    style.id = '__progress_overlay_scroll_style';
                    style.textContent = `
                        #__progress_cards::-webkit-scrollbar { display: none; }
                        #__progress_cards { scrollbar-width: none; }
                    `;
                    document.head.appendChild(style);
                }
            },

            ensureCard(key, title = 'Uploading') {
                this.init();
                if (this.cards[key]) {
                    return this.cards[key].el;
                }

                const container = this.overlay.querySelector('#__progress_cards');
                const card = document.createElement('div');
                card.className = 'bg-white rounded-lg p-3 border border-gray-200 shadow-sm transition-all duration-300';
                card.innerHTML = `
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <div class="truncate font-medium file-name text-xs text-gray-800" title="${escapeHtml(title)}">
                                ${escapeHtml(title)}
                            </div>
                        </div>
                        <div class="ml-3 flex-shrink-0">
                            <div class="h-3 w-3 flex items-center justify-center spinner-wrap">
                                <svg class="animate-spin h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden mt-1 border border-gray-200/50">
                        <div class="progress-bar bg-gray-600 transition-all duration-300" style="width:0%;height:100%"></div>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <div class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Uploading</div>
                        <div class="text-xs text-gray-600 progress-percent">0%</div>
                    </div>
                `;
                container.appendChild(card);
                this.cards[key] = {
                    el: card,
                    lastPercent: 0,
                    errored: false
                };
                return card;
            },

            updateProgress(key, percent) {
                const meta = this.cards[key];
                if (!meta) return;
                if (meta.errored) return;
                const bar = meta.el.querySelector('.progress-bar');
                const pEl = meta.el.querySelector('.progress-percent');
                const pct = Math.max(0, Math.min(100, Math.round(percent)));
                meta.lastPercent = pct;
                if (bar) bar.style.width = pct + '%';
                if (pEl) pEl.textContent = pct + '%';
            },

            markComplete(key) {
                const meta = this.cards[key];
                if (!meta) return;
                if (meta.errored) {
                    setTimeout(() => {
                        try {
                            meta.el.remove();
                        } catch (e) {}
                        delete this.cards[key];
                        this.maybeClose();
                    }, 800);
                    return;
                }

                const bar = meta.el.querySelector('.progress-bar');
                const pEl = meta.el.querySelector('.progress-percent');
                if (bar) {
                    bar.style.width = '100%';
                    bar.classList.add('bg-green-500');
                    bar.classList.remove('bg-blue-500');
                }
                if (pEl) pEl.textContent = 'Completed';
                const spinner = meta.el.querySelector('.spinner-wrap');
                if (spinner) spinner.style.display = 'none';

                setTimeout(() => {
                    meta.el.classList.add('opacity-0', 'scale-95');
                    setTimeout(() => {
                        try {
                            meta.el.remove();
                        } catch (e) {}
                        delete this.cards[key];
                        this.maybeClose();
                    }, 300);
                }, 1100);
            },

            markError(key, msg = 'Upload failed') {
                const meta = this.cards[key];
                if (!meta) return;

                meta.errored = true;

                const spinner = meta.el.querySelector('.spinner-wrap');
                if (spinner) spinner.style.display = 'none';

                meta.el.className =
                    'bg-gradient-to-r from-red-50 to-rose-50 rounded-lg p-3 border border-red-200 transition-all duration-300';

                const pEl = meta.el.querySelector('.progress-percent');
                if (pEl) pEl.textContent = msg;

                const bar = meta.el.querySelector('.progress-bar');
                const width = (typeof meta.lastPercent === 'number') ? meta.lastPercent : 0;
                if (bar) {
                    bar.style.width = Math.max(0, Math.min(100, width)) + '%';
                    bar.classList.remove('bg-gray-600', 'bg-green-500', 'bg-blue-500');
                    bar.classList.add('bg-red-500');
                }

                setTimeout(() => {
                    try {
                        meta.el.remove();
                    } catch (e) {}
                    delete this.cards[key];
                    this.maybeClose();
                }, 2200);
            },

            maybeClose() {
                if (Object.keys(this.cards).length === 0 && this.overlay) {
                    this.overlay.classList.add('opacity-0', 'scale-95');
                    setTimeout(() => {
                        try {
                            this.overlay.remove();
                        } catch (e) {}
                        this.overlay = null;
                    }, 300);
                }
            }
        };

        function escapeHtml(str = '') {
            return String(str).replace(/[&<>"'`=\/]/g, function(s) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                    '=': '&#x3D;'
                })[s];
            });
        }

        document.addEventListener('livewire-upload-start', (e) => {
            const input = e.target;
            const key = `lw-${input.dataset.field || input.id || Math.random().toString(36).slice(2,7)}`;
            const label = input.dataset.label || (input.dataset.field || input.id);
            const fileName = (input.files && input.files[0]) ? input.files[0].name : 'upload';
            ProgressOverlay.ensureCard(key, label + ' — ' + fileName);
        });

        document.addEventListener('livewire-upload-progress', (e) => {
            const input = e.target;
            const key = `lw-${input.dataset.field || input.id || Math.random().toString(36).slice(2,7)}`;
            const percent = e.detail?.progress ?? 0;
            ProgressOverlay.updateProgress(key, percent);
        });

        document.addEventListener('livewire-upload-finish', (e) => {
            const input = e.target;
            const key = `lw-${input.dataset.field || input.id || Math.random().toString(36).slice(2,7)}`;
            ProgressOverlay.markComplete(key);
            input.value = '';
        });

        document.addEventListener('livewire-upload-error', (e) => {
            const input = e.target;
            const key = `lw-${input.dataset.field || input.id || Math.random().toString(36).slice(2,7)}`;
            ProgressOverlay.markError(key, 'Upload error');
        });

        document.addEventListener('FilePond:addfile', (e) => {
            const fp = e.detail.file;

            // Skip existing files loaded during edit mode
            if (fp.getMetadata('old') === true) return;

            const id = 'fp-' + (fp.id || fp.file?.name || Math.random().toString(36).slice(2, 6));
            const name = fp.file?.name || fp.filename || 'file';
            ProgressOverlay.ensureCard(id, name);
        });

        document.addEventListener('FilePond:processfileprogress', (e) => {
            const fp = e.detail.file;
            const id = 'fp-' + (fp.id || fp.file?.name || '');
            let p = e.detail?.progress ?? 0;
            if (p <= 1) p = Math.round(p * 100);
            ProgressOverlay.updateProgress(id, p);
        });

        document.addEventListener('FilePond:processfile', (e) => {
            const fp = e.detail.file;
            const id = 'fp-' + (fp.id || fp.file?.name || '');
            ProgressOverlay.markComplete(id);
        });

        document.addEventListener('FilePond:processfileabort', (e) => {
            const fp = e.detail.file;
            const id = 'fp-' + (fp.id || fp.file?.name || '');
            ProgressOverlay.markError(id, 'Aborted');
        });

        document.addEventListener('FilePond:error', (e) => {
            const fp = e.detail?.file;
            const id = fp ? ('fp-' + (fp.id || fp.file?.name || '')) : null;
            if (id) ProgressOverlay.markError(id, 'Failed');
        });
    </script>
@endscript
