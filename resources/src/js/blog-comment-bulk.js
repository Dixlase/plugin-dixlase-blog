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
 * Alpine.js component for the comments screen: the header checkbox
 * selects or clears every row checkbox (data-comment-select).
 */
export default function blogCommentBulk() {
    return {
        toggleAll() {
            const checked = this.$refs.all.checked;
            this.$root.querySelectorAll('[data-comment-select]').forEach((box) => {
                box.checked = checked;
            });
        },
    };
}
