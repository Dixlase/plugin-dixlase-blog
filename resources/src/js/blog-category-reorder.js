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
 * Alpine.js component for the categories admin tree drag-and-drop.
 *
 * Wires up one Sortable instance per <ul class="category-tree"> container,
 * all sharing the group name "blog-categories" so items can be dragged
 * between parents (re-parenting). After every drop, walks the DOM in
 * pre-order to produce a flat list of { id, parent_id, display_order }
 * and POSTs it to the reorder endpoint. The server saves each item via
 * Eloquent so the BlogCategory saving / saved hooks recompute depth and
 * cycle-check.
 *
 * Usage:
 *   <div x-data="blogCategoryReorder({ saveUrl, csrfToken })">
 *       <ul class="category-tree" data-parent-id="">
 *           <li data-category-id="1">
 *               <div>... row content with .drag-handle ...</div>
 *               <ul class="category-tree" data-parent-id="1">...</ul>
 *           </li>
 *       </ul>
 *   </div>
 */
export default function blogCategoryReorder(config) {
    return {
        saveUrl: config?.saveUrl ?? '',
        csrfToken: config?.csrfToken ?? '',
        saving: false,
        savedAt: null,
        error: null,
        sortables: [],

        init() {
            this.attachSortables();
        },

        destroy() {
            this.sortables.forEach((s) => s.destroy());
            this.sortables = [];
        },

        attachSortables() {
            this.destroy();
            const containers = this.$el.querySelectorAll('.category-tree');
            containers.forEach((container) => {
                const instance = Sortable.create(container, {
                    group: 'blog-categories',
                    handle: '.drag-handle',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    invertSwap: true,
                    ghostClass: 'opacity-40',
                    chosenClass: 'ring-2',
                    onEnd: () => this.persist(),
                });
                this.sortables.push(instance);
            });
        },

        async persist() {
            if (!this.saveUrl) {
                return;
            }
            this.saving = true;
            this.error = null;

            const items = this.serialize();

            try {
                const response = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ items }),
                });

                if (!response.ok) {
                    let message = 'Save failed';
                    try {
                        const data = await response.json();
                        if (data?.error) {
                            message = data.error;
                        } else if (data?.message) {
                            message = data.message;
                        }
                    } catch (_) { /* keep default */ }
                    throw new Error(message);
                }

                this.savedAt = new Date();
            } catch (e) {
                this.error = e.message || String(e);
            } finally {
                this.saving = false;
            }
        },

        /**
         * Walk the DOM in pre-order and emit a flat list of items so the
         * server can persist them top-down. Each item's display_order is
         * (sibling_index + 1) * 10, leaving room for future fine-grained
         * inserts without renumbering.
         */
        serialize() {
            const items = [];
            const walk = (ul, parentId) => {
                if (!ul) return;
                const lis = ul.querySelectorAll(':scope > li[data-category-id]');
                lis.forEach((li, index) => {
                    const id = parseInt(li.dataset.categoryId, 10);
                    if (Number.isNaN(id)) return;
                    items.push({
                        id,
                        parent_id: parentId,
                        display_order: (index + 1) * 10,
                    });
                    const childUl = li.querySelector(':scope > .category-tree');
                    if (childUl) {
                        walk(childUl, id);
                    }
                });
            };
            const root = this.$el.querySelector(':scope > .category-tree');
            walk(root, null);
            return items;
        },
    };
}
