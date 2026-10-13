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
 * Alpine.js component for the post-edit "#tag space-confirmed" tag input.
 *
 * Behaviour matches the spec agreed during Phase 3 planning:
 *   - Typing a word and pressing Space (or Enter) commits it as a chip.
 *   - A leading "#" is stripped so users can type "#foo " or "foo " and
 *     get the same tag.
 *   - Backspace on an empty input removes the most recent chip — same as
 *     Slack / GitHub / similar tag UIs, so the keyboard-first editing flow
 *     does not require reaching for the mouse.
 *   - Duplicates within the same post are silently ignored.
 *   - Each chip emits a hidden <input name="tags[]"> so the form posts
 *     a normal array; BlogPostService upserts unknown tags by slug.
 *
 * Usage:
 *   <div x-data="blogTagInput" data-tags="{{ json_encode($tagNames) }}">...</div>
 */
export default function blogTagInput() {
    return {
        tags: [],
        currentInput: '',

        init() {
            try {
                const initial = JSON.parse(this.$el.dataset.tags || '[]');
                this.tags = Array.isArray(initial) ? initial : [];
            } catch (e) {
                this.tags = [];
            }
        },

        addTag() {
            const raw = this.currentInput.trim();
            if (raw === '') {
                this.currentInput = '';
                return;
            }
            // Strip a leading "#" (or several) so "#foo" and "foo" both produce "foo".
            const tag = raw.replace(/^#+/, '').trim();
            if (tag !== '' && !this.tags.includes(tag)) {
                this.tags.push(tag);
            }
            this.currentInput = '';
        },

        removeTag(index) {
            if (index >= 0 && index < this.tags.length) {
                this.tags.splice(index, 1);
            }
        },

        onKeydown(event) {
            // Space and Enter both commit the current word as a tag.
            if (event.key === ' ' || event.key === 'Spacebar' || event.key === 'Enter') {
                event.preventDefault();
                this.addTag();
                return;
            }
            // Backspace on empty input pops the last chip — mirrors Slack/GitHub UX.
            if (event.key === 'Backspace' && this.currentInput === '' && this.tags.length > 0) {
                event.preventDefault();
                this.tags.pop();
            }
        },

        onBlur() {
            // Commit any pending word so the form does not lose unfinished input.
            if (this.currentInput.trim() !== '') {
                this.addTag();
            }
        },
    };
}
