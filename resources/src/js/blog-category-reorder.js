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
 * cycle-check. Drops deeper than data-max-depth are refused while
 * dragging; the server refuses them too.
 *
 * Usage:
 *   <div x-data="blogCategoryReorder" data-save-url="..." data-csrf-token="...">
 *       <ul class="category-tree" data-parent-id="">
 *           <li data-category-id="1">
 *               <div>... row content with .drag-handle ...</div>
 *               <ul class="category-tree" data-parent-id="1">...</ul>
 *           </li>
 *       </ul>
 *   </div>
 */
export default function blogCategoryReorder() {
    return {
        saveUrl: '',
        csrfToken: '',
        maxDepth: 2,
        // The open inline form: 'create', 'child-{id}', 'edit-{id}' or null.
        openForm: null,
        saving: false,
        savedAt: null,
        error: null,
        sortables: [],

        init() {
            this.saveUrl = this.$el.dataset.saveUrl || '';
            this.csrfToken = this.$el.dataset.csrfToken || '';
            const max = parseInt(this.$el.dataset.maxDepth, 10);
            this.maxDepth = Number.isNaN(max) ? 2 : max;
            // A failed submit comes back with its form open.
            this.openForm = this.$el.dataset.reopen || null;
            this.attachSortables();
        },

        isOpen(key) {
            return this.openForm === key;
        },

        toggle(key) {
            this.openForm = this.openForm === key ? null : key;
        },

        toggleCreate() {
            this.toggle('create');
        },

        toggleChild(id) {
            this.toggle('child-' + id);
        },

        toggleEdit(id) {
            this.toggle('edit-' + id);
        },

        closeForms() {
            this.openForm = null;
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
                    ghostClass: 'dixlase-blog-category-ghost',
                    chosenClass: 'ring-2',
                    onStart: (evt) => this.startDrag(evt.item),
                    onMove: (evt) => this.allowDrop(evt.dragged, evt.to),
                    onEnd: () => this.endDrag(),
                });
                this.sortables.push(instance);
            });
        },

        /**
         * Mark the lists the dragged row may land in, so empty child lists
         * open up as drop zones only where the result stays within maxDepth.
         */
        startDrag(item) {
            this.$el.classList.add('is-dragging');
            const height = this.subtreeHeight(item);
            this.$el.querySelectorAll('.category-tree').forEach((list) => {
                const fits = !item.contains(list) && this.listDepth(list) + height <= this.maxDepth;
                list.classList.toggle('can-accept', fits);
            });
        },

        allowDrop(item, list) {
            this.$el.querySelectorAll('.category-tree.is-drop-target').forEach((el) => {
                el.classList.remove('is-drop-target');
            });
            if (this.listDepth(list) + this.subtreeHeight(item) > this.maxDepth) {
                return false;
            }
            list.classList.add('is-drop-target');
            return true;
        },

        endDrag() {
            this.$el.classList.remove('is-dragging');
            this.$el.querySelectorAll('.category-tree').forEach((list) => {
                list.classList.remove('can-accept', 'is-drop-target');
            });
            this.refreshRows();
            this.persist();
        },

        /** Depth a row placed in this list would have (0 = top level). */
        listDepth(list) {
            let depth = 0;
            let node = list.parentElement;
            while (node && node !== this.$el) {
                if (node.matches('li[data-category-id]')) {
                    depth++;
                }
                node = node.parentElement;
            }
            return depth;
        },

        /** Levels of rows nested below this row. */
        subtreeHeight(item) {
            let height = 0;
            item.querySelectorAll('li[data-category-id]').forEach((li) => {
                const levels = this.listDepth(li.parentElement) - this.listDepth(item.parentElement);
                height = Math.max(height, levels);
            });
            return height;
        },

        /**
         * After a drop, show "+" only where a child still fits and "Move up
         * a level" only below the top level, as the server renders them.
         */
        refreshRows() {
            this.$el.querySelectorAll('li[data-category-id]').forEach((li) => {
                const depth = this.listDepth(li.parentElement);
                li.querySelectorAll(':scope > div > div > [data-when="can-add-child"]').forEach((el) => {
                    el.hidden = depth >= this.maxDepth;
                });
                li.querySelectorAll(':scope > div > div > [data-when="can-outdent"]').forEach((el) => {
                    el.hidden = depth === 0;
                });
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
