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
 * Script for the public blog pages, kept apart from the admin bundle
 * (app.js) so readers do not download the editor.
 *
 * Copy-link buttons: a button with data-dixlase-blog-copy="<url>" copies
 * that URL and shows its data-copied-label for a moment. A plain listener
 * rather than an Alpine component, so it works whatever the theme loads.
 */

const COPIED_FOR_MS = 2000;

document.addEventListener('click', async (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-dixlase-blog-copy]') : null;
    if (!button || !navigator.clipboard) {
        return;
    }

    try {
        await navigator.clipboard.writeText(button.dataset.dixlaseBlogCopy);
    } catch {
        return;
    }

    const label = button.textContent;
    button.textContent = button.dataset.copiedLabel || label;
    button.setAttribute('data-copied', '');
    window.setTimeout(() => {
        button.textContent = label;
        button.removeAttribute('data-copied');
    }, COPIED_FOR_MS);
});
