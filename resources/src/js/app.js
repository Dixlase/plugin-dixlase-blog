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

import blogCategoryReorder from './blog-category-reorder.js';
import blogPostForm from './blog-post-form.js';
import blogFeaturedPreview from './blog-featured-preview.js';
import blogPreviewTitle from './blog-preview-title.js';
import blogSidebarWidgets from './blog-sidebar-widgets.js';
import blogCommentBulk from './blog-comment-bulk.js';
import blogListingLayout from './blog-listing-layout.js';
import blogSourceLanguage from './blog-source-language.js';

document.addEventListener('alpine:init', () => {
    if (window.Alpine) {
        window.Alpine.data('blogCategoryReorder', blogCategoryReorder);
        window.Alpine.data('blogPostForm', blogPostForm);
        window.Alpine.data('blogFeaturedPreview', blogFeaturedPreview);
        window.Alpine.data('blogPreviewTitle', blogPreviewTitle);
        window.Alpine.data('blogSidebarWidgets', blogSidebarWidgets);
        window.Alpine.data('blogCommentBulk', blogCommentBulk);
        window.Alpine.data('blogListingLayout', blogListingLayout);
        window.Alpine.data('blogSourceLanguage', blogSourceLanguage);
    }
});
