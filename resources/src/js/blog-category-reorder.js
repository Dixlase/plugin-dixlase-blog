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
 * The move buttons on each row (up, down, into the row above) make the same
 * changes without dragging, for keyboard and touch users, and save the
 * tree the same way.
 *
 * Usage:
 *   <div x-data="blogCategoryReorder" data-save-url="..." data-csrf-token="...">
 *       <ul class="category-tree" data-parent-id="" data-tree-root>
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
        // A move made while a save is in flight; saved once that one ends.
        saveAgain: false,
        savedAt: null,
        error: null,
        sortables: [],
        // The component element. Inside a method called from @click, Alpine's
        // $el is the clicked element, so the methods use this instead.
        root: null,

        init() {
            this.root = this.$el;
            this.saveUrl = this.root.dataset.saveUrl || '';
            this.csrfToken = this.root.dataset.csrfToken || '';
            const max = parseInt(this.root.dataset.maxDepth, 10);
            this.maxDepth = Number.isNaN(max) ? 2 : max;
            // A failed submit comes back with its form open.
            this.openForm = this.root.dataset.reopen || null;
            this.attachSortables();
            this.refreshRows();
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
            const containers = this.root.querySelectorAll('.category-tree');
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
            this.root.classList.add('is-dragging');
            const height = this.subtreeHeight(item);
            this.root.querySelectorAll('.category-tree').forEach((list) => {
                const fits = !item.contains(list) && this.listDepth(list) + height <= this.maxDepth;
                list.classList.toggle('can-accept', fits);
            });
        },

        allowDrop(item, list) {
            this.root.querySelectorAll('.category-tree.is-drop-target').forEach((el) => {
                el.classList.remove('is-drop-target');
            });
            if (this.listDepth(list) + this.subtreeHeight(item) > this.maxDepth) {
                return false;
            }
            list.classList.add('is-drop-target');
            return true;
        },

        endDrag() {
            this.root.classList.remove('is-dragging');
            this.root.querySelectorAll('.category-tree').forEach((list) => {
                list.classList.remove('can-accept', 'is-drop-target');
            });
            this.refreshRows();
            this.persist();
        },

        /** Depth a row placed in this list would have (0 = top level). */
        listDepth(list) {
            let depth = 0;
            let node = list.parentElement;
            while (node && node !== this.root) {
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
         * After a drop or a move, show "+" only where a child still fits and
         * "Move up a level" only below the top level, as the server renders
         * them, and enable the move buttons only where the move is possible.
         */
        refreshRows() {
            this.root.querySelectorAll('li[data-category-id]').forEach((li) => {
                const depth = this.listDepth(li.parentElement);
                const buttons = (when) => li.querySelectorAll(':scope > div > div > [data-when="' + when + '"]');
                buttons('can-add-child').forEach((el) => {
                    el.hidden = depth >= this.maxDepth;
                });
                buttons('can-outdent').forEach((el) => {
                    el.hidden = depth === 0;
                });
                buttons('can-move-earlier').forEach((el) => {
                    el.disabled = this.siblingRow(li, -1) === null;
                });
                buttons('can-move-later').forEach((el) => {
                    el.disabled = this.siblingRow(li, 1) === null;
                });
                buttons('can-move-into-above').forEach((el) => {
                    el.hidden = this.siblingRow(li, -1) === null
                        || depth + 1 + this.subtreeHeight(li) > this.maxDepth;
                });
            });
        },

        /** The row before (-1) or after (1) this one in the same list, or null. */
        siblingRow(li, step) {
            let node = step < 0 ? li.previousElementSibling : li.nextElementSibling;
            while (node && !node.matches('li[data-category-id]')) {
                node = step < 0 ? node.previousElementSibling : node.nextElementSibling;
            }
            return node;
        },

        row(id) {
            return this.root.querySelector('li[data-category-id="' + id + '"]');
        },

        moveEarlier(id) {
            const li = this.row(id);
            const before = li && this.siblingRow(li, -1);
            if (!before) return;
            li.parentElement.insertBefore(li, before);
            this.afterMove(li, 'earlier');
        },

        moveLater(id) {
            const li = this.row(id);
            const after = li && this.siblingRow(li, 1);
            if (!after) return;
            li.parentElement.insertBefore(after, li);
            this.afterMove(li, 'later');
        },

        /** Make the row the last child of the row above it. */
        moveIntoAbove(id) {
            const li = this.row(id);
            const above = li && this.siblingRow(li, -1);
            const list = above && above.querySelector(':scope > .category-tree');
            if (!list || this.listDepth(list) + this.subtreeHeight(li) > this.maxDepth) return;
            list.appendChild(li);
            this.afterMove(li, 'into-above');
        },

        /**
         * Save the moved tree like a drop, and keep focus on the pressed
         * button (moving a node drops focus) or, when that button no longer
         * applies, on the first move button of the row that does.
         */
        afterMove(li, move) {
            this.refreshRows();
            const usable = (el) => el && !el.disabled && !el.hidden;
            const pressed = li.querySelector(':scope > div > div > [data-move="' + move + '"]');
            const fallback = Array.from(li.querySelectorAll(':scope > div > div > [data-move]')).find(usable);
            const target = usable(pressed) ? pressed : fallback;
            if (target) target.focus();
            this.persist();
        },

        async persist() {
            if (!this.saveUrl) {
                return;
            }
            // Pressing a move button again before the save answers must not
            // let an older tree land after a newer one.
            if (this.saving) {
                this.saveAgain = true;
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
                        // Core renders JSON errors as { error: { code, message } }.
                        if (typeof data?.error?.message === 'string') {
                            message = data.error.message;
                        } else if (typeof data?.error === 'string') {
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
                if (this.saveAgain) {
                    this.saveAgain = false;
                    this.persist();
                }
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
            // The root list sits inside a wrapper, so it is not a direct child.
            const root = this.root.querySelector('[data-tree-root]');
            walk(root, null);
            return items;
        },
    };
}
