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
 * Alpine.js component around the featured image picker of the post form:
 * sends the chosen image to the live preview iframe ("updateFeatured"), so
 * the preview follows when the image is chosen, changed or removed. It
 * talks to the iframe directly, so it works with whichever editor draws
 * the preview pane (the iframe carries x-ref="previewIframe").
 *
 * Usage:
 *   <div x-data="blogFeaturedPreview" data-input-id="featured_media_id">
 *       ...media picker...
 *   </div>
 */
export default function blogFeaturedPreview() {
    return {
        inputId: '',
        url: null,
        alt: '',
        // Declared here so they stay on this component. Alpine stores a
        // property assigned without a declaration on the outermost x-data,
        // where it overwrote blogPreviewTitle's (#203).
        _input: null,
        _onSelected: null,
        _onChange: null,
        _onMessage: null,

        init() {
            this.inputId = this.$el.dataset.inputId || '';

            this._onSelected = (event) => {
                if (event.detail?.inputId !== this.inputId) {
                    return;
                }
                const media = event.detail.media || {};
                this.url = typeof event.detail.url === 'string' ? event.detail.url : '';
                this.alt = media.alt_text || media.name || '';
                this.send();
            };
            document.addEventListener('media-selected', this._onSelected);

            // Removing the image clears the input and fires change.
            this._input = document.getElementById(this.inputId);
            this._onChange = () => {
                if (this._input && this._input.value === '') {
                    this.url = '';
                    this.alt = '';
                    this.send();
                }
            };
            this._input?.addEventListener('change', this._onChange);

            // A reloaded iframe starts from the saved image; send the
            // current choice again once it is ready.
            this._onMessage = (event) => {
                if (event.origin === window.location.origin && event.data?.type === 'dixlase-preview-ready') {
                    this.send();
                }
            };
            window.addEventListener('message', this._onMessage);
        },

        destroy() {
            document.removeEventListener('media-selected', this._onSelected);
            this._input?.removeEventListener('change', this._onChange);
            window.removeEventListener('message', this._onMessage);
        },

        send() {
            // Nothing chosen in this session: the frame shows the saved image.
            if (this.url === null) {
                return;
            }
            const iframe = document.querySelector('iframe[x-ref="previewIframe"]');
            iframe?.contentWindow?.postMessage(
                { type: 'dixlase-preview-update', action: 'updateFeatured', url: this.url, alt: this.alt },
                window.location.origin,
            );
        },
    };
}
