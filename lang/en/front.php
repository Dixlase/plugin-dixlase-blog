<?php

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
 */

return [

    'index' => [
        'title' => 'Blog',
        'heading' => 'Blog',
        'description' => 'Latest blog posts.',
        'no_posts' => 'No posts have been published yet.',
        'feed_link' => 'RSS feed',
    ],

    'archive' => [
        'category_title' => 'Category: :name',
        'tag_title' => 'Tag: :name',
        'category_kicker' => 'Category',
        'tag_kicker' => 'Tag',
        'no_posts' => 'No posts in this archive yet.',
        'back_to_index' => '← Back to all posts',
        'date_title' => 'Archive: :date',
        'date_kicker' => 'Archive',
        'search_title' => 'Search: :query',
        'search_kicker' => 'Search',
        // Carbon isoFormat patterns for the date archive headings.
        'month_format' => 'MMMM YYYY',
        'day_format' => 'MMMM D, YYYY',
    ],

    'search' => [
        'label' => 'Search the blog',
        'placeholder' => 'Keywords',
        'button' => 'Search',
        'no_results' => 'No posts match your search.',
    ],

    'sidebar' => [
        'label' => 'Blog sidebar',
        'search' => 'Search',
        'categories' => 'Categories',
        'recent' => 'Recent posts',
        'archives' => 'Archives',
        'calendar' => 'Calendar',
        'tags' => 'Tags',
    ],

    'share' => [
        'label' => 'Share',
        'x' => 'X',
        'facebook' => 'Facebook',
        'line' => 'LINE',
        'copy' => 'Copy link',
        'copied' => 'Copied',
    ],

    'breadcrumbs' => [
        'label' => 'Breadcrumb',
        'home' => 'Home',
    ],

    'taxonomy' => [
        'categories' => 'Categories',
        'tags' => 'Tags',
    ],

    'feed_title' => ':site — Blog',
    'feed_description' => 'Latest blog posts from :site.',


    'pagination' => [
        'label' => 'Pagination',
        'previous' => 'Newer posts',
        'next' => 'Older posts',
        'status' => 'Page :current of :last',
    ],

    'post' => [
        'nav_label' => 'More posts',
        'previous' => 'Previous post',
        'next' => 'Next post',
        'related' => 'Related posts',
        'back_to_index' => '← Back to all posts',
        'pinned' => 'Pinned',
    ],
];
