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
 * Editor content is owned by the inner <x-form-content-editor> Alpine
 * component (which keeps its own `content` reactive state via x-model on
 * the textarea). Rather than reaching into that scope, we observe the
 * underlying <textarea name="content"> via plain DOM input events. The
 * title field lives in the right sidebar (a sibling Alpine scope) so we
 * pick that up the same way.
 *
 * Outgoing protocol to the iframe (matches themes::admin.preview-frame
 * in DixlaseOnePage so previewMixin's height reporting works uniformly):
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
        _previewReady: false,

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

            // Run the mixins' own init steps if they expose one.
            if (typeof this.initSplitPane === 'function') this.initSplitPane();
            if (typeof this.initPreview === 'function') this.initPreview();

            // Wait for the inner editor + sidebar inputs to mount so we
            // can attach DOM listeners.
            this.$nextTick(() => this._wireExternalInputs());

            // Wait for the iframe to confirm readiness before pushing the
            // first sync — saves a wasted POST that arrives before the
            // child page's listener is attached.
            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) return;
                if (event.data && event.data.type === 'dixlase-preview-ready') {
                    this._previewReady = true;
                    this.scheduleUpdatePreview(0);
                }
            });
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
            // operator switches between Markdown / HTML radio cards.
            // Listening on window matches how the component dispatches.
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
            this._previewDebounce = setTimeout(() => this.updatePreview(), delay);
        },

        async updatePreview() {
            if (!this._serverPreviewUrl) return;
            if (!this._previewReady) return;

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
                this._postPreviewToIframe(data.html || '', this.title);
            } catch (e) {
                console.warn('Dixlase Blog: preview update failed', e);
            }
        },

        _postPreviewToIframe(html, title) {
            const iframe = this.$refs.previewIframe;
            if (!iframe || !iframe.contentWindow) return;
            iframe.contentWindow.postMessage(
                {
                    type: 'dixlase-preview-update',
                    action: 'updateContent',
                    html,
                    title,
                },
                window.location.origin,
            );
        },
    });
}
