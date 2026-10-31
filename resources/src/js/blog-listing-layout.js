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
 * Alpine.js component for the top page layout on the blog's Listing
 * settings screen. What follows the layout depends on it: the cards'
 * columns and excerpt, or the latest posts' text. The hidden fields are
 * still sent, so their saved values stay.
 *
 * The radio group binds to `layout` (xModel); the saved value comes in on
 * data-layout, so the right card starts checked.
 */
export default function blogListingLayout() {
    return {
        layout: 'cards',

        init() {
            this.layout = this.$el.dataset.layout || 'cards';
        },

        get latest() {
            return this.layout === 'full';
        },

        get cards() {
            return ! this.latest;
        },
    };
}
