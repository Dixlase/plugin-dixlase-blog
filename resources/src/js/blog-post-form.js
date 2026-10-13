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
 * Alpine.js component for the post form: tells the admin layout that the
 * page has a right sidebar, so the centre column makes room for it.
 */
export default function blogPostForm() {
    return {
        init() {
            this.$dispatch('right-sidebar-active');
        },
    };
}
