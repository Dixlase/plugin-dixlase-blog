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


import Sortable from 'sortablejs';

/**
 * Alpine.js component for the sidebar items on the blog settings screen.
 *
 * Each row carries a hidden sidebar_widgets[] input, so dragging a row
 * reorders the inputs and the form submits the new order as it is; there
 * is nothing to keep in sync.
 */
export default function blogSidebarWidgets() {
    return {
        sortable: null,

        init() {
            this.sortable = Sortable.create(this.$refs.list, {
                handle: '[data-drag-handle]',
                animation: 150,
            });
        },

        destroy() {
            if (this.sortable) {
                this.sortable.destroy();
                this.sortable = null;
            }
        },
    };
}
