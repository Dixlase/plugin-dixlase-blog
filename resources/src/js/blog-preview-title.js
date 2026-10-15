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
 *
 * Unless you have entered into a commercial license agreement, this
 * file is governed by the GPL terms below.
 */

/**
 * Alpine.js component around the post title field: sends the title to the
 * live preview iframe ("updateTitle") as it is typed, and again when the
 * frame reloads. The editor's preview pane renders the body only.
 *
 * Usage:
 *   <div x-data="blogPreviewTitle" data-input-id="title">...title field...</div>
 */
export default function blogPreviewTitle() {
    return {
        init() {
            this._input = document.getElementById(this.$el.dataset.inputId || '');
            this._onInput = () => this.send();
            this._input?.addEventListener('input', this._onInput);

            this._onMessage = (event) => {
                if (event.origin === window.location.origin && event.data?.type === 'dixlase-preview-ready') {
                    this.send();
                }
            };
            window.addEventListener('message', this._onMessage);
        },

        destroy() {
            this._input?.removeEventListener('input', this._onInput);
            window.removeEventListener('message', this._onMessage);
        },

        send() {
            const iframe = document.querySelector('iframe[x-ref="previewIframe"]');
            iframe?.contentWindow?.postMessage(
                { type: 'dixlase-preview-update', action: 'updateTitle', title: this._input?.value ?? '' },
                window.location.origin,
            );
        },
    };
}
