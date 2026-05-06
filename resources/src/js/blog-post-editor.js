/**
 * This file is part of Dixlase Blog.
 *
 * Copyright (C) 2026 exc-D inc.
 * https://exc-d.com
 *
 * Dixlase Blog is dual-licensed. You may use this file under either:
 *
 *   (a) the GNU General Public License version 3 or later, as published
 *       by the Free Software Foundation; or
 *
 *   (b) a commercial license agreement obtained from exc-D inc.
 */

/**
 * Alpine component that drives the post-edit screen's split-pane editor
 * and live preview iframe. Built on top of core's runtime mixins
 * (window.Dixlase.mixins.{previewMixin,splitPaneMixin,mergeMixins}) so
 * we get the device-mode toggles, scaling, draggable divider etc. for
 * free without bundling the mixin code into the plugin's own bundle —
 * core's @api contract explicitly disallows that import.
 *
 * Method-name contract with previewMixin (must match exactly):
 *   - this.previewReady                — mixin sets to true on iframe ready
 *   - this.sendContentToPreview()      — mixin calls on first ready;
 *                                        we also call it after each edit
 *   - this.$refs.previewIframe         — provided by <x-content-editor.preview-pane>
 *   - this.$refs.previewContainer      — provided by the same component
 *
 * Editor content is owned by the inner <x-form-content-editor> Alpine
 * component (which keeps its own `content` reactive state via x-model on
 * the textarea). Rather than reaching into that scope, we observe the
 * underlying <textarea name="content"> via plain DOM input events. The
 * title field lives in the right sidebar (a sibling Alpine scope) so we
 * pick that up the same way.
 *
 * Outgoing iframe protocol (matches DixlaseOnePage's preview-frame):
 *   { type: 'dixlase-preview-update', action: 'updateContent', html, title }
 */
export default function blogPostEditor(config) {
    const dx = window.Dixlase && window.Dixlase.mixins;

    // Graceful degradation: if core mixins are not on the page (e.g. a
    // theme or layout removed them) the form should still render and
    // submit — the live preview just becomes a no-op.
    if (!dx || !dx.previewMixin || !dx.splitPaneMixin || !dx.mergeMixins) {
        console.warn('Dixlase Blog: window.Dixlase.mixins unavailable; preview disabled.');
        return {
            previewVisible: false,
            isHorizontal: true,
            splitRatio: 0.5,
            isDragging: false,
            isResizingPreview: false,
            togglePreview() { /* no-op */ },
            init() { this.$dispatch('right-sidebar-active'); },
        };
    }

    const base = dx.mergeMixins(dx.splitPaneMixin(), dx.previewMixin(config));

    return dx.mergeMixins(base, {
        // ===== Editor-bound state =====
        editorType: config.editorType || 'markdown',
        content: config.initialContent || '',
        title: config.initialTitle || '',

        // ===== Internals =====
        _previewDebounce: null,
        _csrfToken: config.csrfToken || '',
        _serverPreviewUrl: config.serverPreviewUrl || '',

        // Per-editor-type debounce. HTML feels slow if delayed too long;
        // Markdown re-parse is heavier so a slightly longer wait keeps
        // typing responsive.
        _debounceFor(editorType) {
            switch (editorType) {
                case 'html': return 150;
                case 'markdown': return 250;
                case 'blade': return 400;
                default: return 250;
            }
        },

        init() {
            // Tell the admin layout we have a sidebar so #admin-main-content
            // gets lg:mr-80 and the centre column does not draw under it.
            this.$dispatch('right-sidebar-active');

            // Order matches FrontPage's editor: initPreview *before*
            // initSplitPane. previewMixin attaches the message listener
            // for dixlase-preview-ready and sets up the container width
            // observer; splitPaneMixin then wires up the drag divider
            // and persists splitRatio.
            this.initPreview();
            this.initSplitPane();

            // Attach DOM listeners on the inner editor / sidebar inputs
            // after Alpine has rendered them.
            this.$nextTick(() => this._wireExternalInputs());
        },

        _wireExternalInputs() {
            // <x-form-content-editor> owns its textarea via x-model, but
            // input events still bubble to document so DOM listeners are
            // safe even though the textarea is in a nested Alpine scope.
            const contentTextarea = document.querySelector('textarea[name="content"]');
            if (contentTextarea) {
                contentTextarea.addEventListener('input', () => {
                    this.content = contentTextarea.value;
                    this.scheduleUpdatePreview();
                });
            }

            // Title lives in the right sidebar, which is its own Alpine
            // scope; same DOM-listener strategy applies.
            const titleInput = document.querySelector('input[name="title"]');
            if (titleInput) {
                titleInput.addEventListener('input', () => {
                    this.title = titleInput.value;
                    this.scheduleUpdatePreview();
                });
            }

            // form-content-editor dispatches editor-type-changed when the
            // operator switches between Markdown / HTML radio cards
            // (create mode only — the selector is hidden in edit mode).
            window.addEventListener('editor-type-changed', (event) => {
                if (event.detail && event.detail.editorType) {
                    this.editorType = event.detail.editorType;
                    this.scheduleUpdatePreview();
                }
            });
        },

        scheduleUpdatePreview(delayOverride) {
            if (this._previewDebounce) clearTimeout(this._previewDebounce);
            const delay = typeof delayOverride === 'number'
                ? delayOverride
                : this._debounceFor(this.editorType);
            this._previewDebounce = setTimeout(() => this.sendContentToPreview(), delay);
        },

        /**
         * Called by previewMixin::initPreview() when the iframe posts
         * dixlase-preview-ready, and by scheduleUpdatePreview() on
         * every debounced edit. Method name is fixed by previewMixin's
         * runtime contract.
         */
        async sendContentToPreview() {
            if (!this._serverPreviewUrl) return;
            // previewReady is owned by previewMixin; only send once the
            // iframe shell has signalled ready, otherwise the postMessage
            // is dropped on the floor.
            if (!this.previewReady) return;

            try {
                const response = await fetch(this._serverPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this._csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        content: this.content,
                        editor_type: this.editorType,
                    }),
                });
                if (!response.ok) return;
                const data = await response.json();
                this._postToIframe('updateContent', { html: data.html || '', title: this.title });
            } catch (e) {
                console.warn('Dixlase Blog: preview update failed', e);
            }
        },

        _postToIframe(action, payload) {
            const iframe = this.$refs.previewIframe;
            if (!iframe || !iframe.contentWindow) return;
            iframe.contentWindow.postMessage(
                {
                    type: 'dixlase-preview-update',
                    action,
                    ...payload,
                },
                window.location.origin,
            );
        },
    });
}
