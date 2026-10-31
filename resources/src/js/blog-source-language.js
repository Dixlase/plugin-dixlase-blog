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
 * Source-language picker on the post edit screen (#133).
 *
 * Shows the confirmation toggle while the chosen language is neither the
 * post's current one nor one it has a translation in: the old language
 * would then have no text of its own.
 */
export default function blogSourceLanguage() {
    return {
        needsConfirmation: false,

        init() {
            this._current = this.$el.dataset.current || '';
            this._translated = (this.$el.dataset.translated || '').split(',').filter(Boolean);
            const select = this.$el.querySelector('select[name="lang"]');
            this.update(select ? select.value : this._current);
        },

        pick(event) {
            if (event.target && event.target.name === 'lang') {
                this.update(event.target.value);
            }
        },

        update(lang) {
            this.needsConfirmation = lang !== this._current && !this._translated.includes(lang);
        },
    };
}
