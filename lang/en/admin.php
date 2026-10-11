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
 *
 * Unless you have entered into a commercial license agreement, this
 * file is governed by the GPL terms below.
 */

return [

    'nav' => [
        'blog' => [
            'text' => 'Blog Management',
            'index' => 'Blog Master',
            'create' => 'Create New',
            'categories' => 'Categories',
            'trash' => 'Trash',
        ],
    ],

    // Auto-resolved page headings. The core AdminInterfaceTrait derives a
    // lookup key from the route name (e.g. dixlase-blog::admin.blog.index
    // → admin.blog.index.heading) and renders it in the admin layout's
    // top bar; keep the keys mirrored against the routes/web.php names.
    'blog' => [
        'index' => ['heading' => 'Posts'],
        'create' => ['heading' => 'New Post'],
        'edit' => ['heading' => 'Edit Post'],
        'trash' => ['heading' => 'Trash'],
        'categories' => [
            'index' => ['heading' => 'Categories'],
        ],
        'revisions' => [
            'index' => ['heading' => 'Revisions'],
            'show' => ['heading' => 'Revision Diff'],
        ],
    ],

    'page_title' => [
        'index' => 'Posts',
        'trash' => 'Trash',
        'create' => 'New Post',
        'edit' => 'Edit Post',
        'categories' => 'Categories',
        'revisions' => 'Revisions',
    ],

    'post' => [
        'title_label' => 'Title',
        'title_placeholder' => 'Enter post title',
        'slug_label' => 'Slug',
        'slug_help' => 'Leave blank to auto-generate from the title.',
        'content_label' => 'Content',
        'excerpt_label' => 'Excerpt',
        'excerpt_help' => 'Used in listings and OG description. Leave blank to auto-generate (140 chars from content).',
        'featured_image_label' => 'Featured image',
        'featured_image_help' => 'Used as the OG image and in listings. Choose from the media library.',
        'status_label' => 'Status',
        'published_at_label' => 'Publish at',
        'published_at_help' => 'When the post should go live (used for scheduled posts).',
        'closed_at_label' => 'Close at',
        'closed_at_help' => 'Optional. Auto-unpublish at this time.',
        'lang_label' => 'Language',
        'lang_help' => 'The language this post is written in. Other languages are added as translations after saving.',
        'editor_type_not_allowed' => 'You cannot use this editor type.',
        'categories_label' => 'Categories',
        'categories_help' => 'A post can belong to multiple categories.',
        'no_categories' => 'No categories yet — create one first.',
        'tags_label' => 'Tags',
        'tags_placeholder' => 'Type a tag, press space',
        'tags_help' => 'Press Space (or Enter) to confirm a tag. Backspace on empty input removes the last tag. Leading # is optional.',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'author' => 'Author',
        'author_unknown' => '(unknown)',
    ],

    'post_index' => [
        'heading' => 'Posts',
        'description' => 'Manage blog posts.',
        'new_button' => 'New post',
        'trash_link' => 'Trash',
        'col_title' => 'Title',
        'col_status' => 'Status',
        'col_categories' => 'Categories',
        'col_published_at' => 'Published',
        'col_actions' => '',
        'no_posts' => 'No posts yet.',
        'edit' => 'Edit',
        'delete' => 'Move to trash',
        'delete_confirm_title' => 'Move to trash',
        'delete_confirm_message' => 'Move this post to trash? It can be restored from the trash page.',
    ],

    'post_form' => [
        'create_heading' => 'New post',
        'edit_heading' => 'Edit post',
        'sidebar_open' => 'Open sidebar',
        'sidebar_close' => 'Close sidebar',
        'publish_section' => 'Publish',
        'classification_section' => 'Categorization',
        'media_section' => 'Featured image',
        'meta_section' => 'Title, slug & excerpt',
        'meta_info_section' => 'Meta',
        'meta_editor_locked_help' => 'The editor type is locked once a post has been saved — switching it would risk losing content fidelity. Create a new post if you need a different editor.',
        'author_info_section' => 'Author info',
        'revision_section' => 'Revisions',
        'revision_link' => 'View history',
        'revision_help' => 'Every save creates a revision (skipped when nothing changed).',
        'preview_button' => 'Preview',
        'preview_show' => 'Show preview',
        'preview_hide' => 'Hide preview',
        'preview_help_edit' => 'Opens the saved version of this post in a new tab. Use the centre toggle for unsaved-change preview.',
        'preview_help_create' => 'Save the post once to enable the public-side preview.',
        'save_confirm_title' => 'Save post',
        'save_confirm_message' => 'Save changes to this post?',
        'back_to_posts' => 'Back to posts',
    ],

    'trash' => [
        'heading' => 'Trash',
        'description' => 'Trashed posts can be restored or permanently deleted.',
        'col_title' => 'Title',
        'col_deleted_at' => 'Deleted',
        'col_actions' => '',
        'no_posts' => 'Trash is empty.',
        'restore' => 'Restore',
        'delete_forever' => 'Delete forever',
        'delete_confirm_title' => 'Delete forever',
        'delete_confirm_message' => 'Permanently delete this post? This cannot be undone.',
        'back_to_posts' => 'Back to posts',
    ],

    'category' => [
        'heading' => 'Categories',
        'description' => 'Manage blog categories.',
        'reorder_help' => 'Drag the handle (⋮⋮) on the left of each row to reorder. Drop a category onto another row to make it a child.',
        'drag_handle_label' => 'Drag to reorder',
        'saving' => 'Saving…',
        'saved' => 'Saved',
        'new_button' => 'New category',
        'col_label' => 'Name',
        'lang_label' => 'Language',
        'lang_help' => 'The language this name is written in. Other languages are added as translations.',
        'col_slug' => 'Slug',
        'col_posts' => 'Posts',
        'col_actions' => '',
        'no_categories' => 'No categories yet.',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'label_label' => 'Name',
        'slug_label' => 'Slug',
        'slug_help' => 'Leave blank to auto-generate from the name.',
        'parent_label' => 'Parent',
        'parent_none' => '(top-level)',
        'delete_confirm_title' => 'Delete category',
        'delete_confirm_message' => 'Delete this category? Posts in this category will lose this categorization.',
    ],

    'revision' => [
        'heading' => 'Revisions',
        'back_to_edit' => 'Back to post',
        'no_revisions' => 'No revisions yet.',
        'created_at' => 'Created',
        'type' => 'Type',
        'creator' => 'Author',
        'note' => 'Note',
        'note_placeholder' => 'Optional note about this revision',
        'note_help' => 'Brief description of what changed.',
        'note_save' => 'Save note',
        'note_confirm_title' => 'Save note',
        'note_confirm_message' => 'Save the note for this revision?',
        'protect' => 'Protect',
        'protect_help' => 'Protected revisions are excluded from auto-pruning when the retention limit is reached.',
        'protect_label_on' => 'Protected',
        'protect_label_off' => 'Not protected',
        'protect_enable' => 'Protect',
        'protect_disable' => 'Unprotect',
        'protect_count_summary' => ':protected protected / :retention retained',
        'protect_count_summary_over' => ':protected protected (over :retention retention)',
        'actions' => 'Actions',
        'view_diff' => 'View diff',
        'restore' => 'Restore',
        'restore_confirm_title' => 'Restore revision',
        'restore_confirm_message' => 'Restore this revision? The current state is backed up first.',
        'unknown_user' => '(unknown)',
        'type_auto' => 'Auto',
        'type_manual' => 'Manual',
        'type_restore_backup' => 'Pre-restore backup',
        'diff_heading' => 'Diff',
        'diff_no_changes' => 'No differences from the current state.',
        'diff_meta_heading' => 'Metadata',
        'diff_left_label' => 'Revision',
        'diff_right_label' => 'Current',
        'diff_field_title' => 'Title',
        'diff_field_slug' => 'Slug',
        'diff_field_content' => 'Content',
        'diff_field_excerpt' => 'Excerpt',
        'diff_field_featured_media_id' => 'Featured image',
        'diff_field_editor_type' => 'Editor',
        'diff_field_status' => 'Status',
        'diff_field_lang' => 'Language',
        'diff_field_published_at' => 'Publish at',
        'diff_field_closed_at' => 'Close at',
        'diff_field_user_id' => 'Author',
        'pivot_heading' => 'Categories & tags',
        'pivot_added' => 'Added',
        'pivot_removed' => 'Removed',
    ],

    'flash' => [
        'post_created' => 'Post created.',
        'post_updated' => 'Post updated.',
        'post_trashed' => 'Post moved to trash.',
        'post_restored' => 'Post restored.',
        'post_deleted' => 'Post permanently deleted.',
        'category_created' => 'Category created.',
        'category_updated' => 'Category updated.',
        'category_deleted' => 'Category deleted.',
        'category_has_children' => 'This category cannot be deleted because it has child categories. Move or delete them first.',
        'revision_restored' => 'Revision restored.',
        'revision_protect_enabled' => 'Revision protected.',
        'revision_protect_disabled' => 'Revision protection removed.',
        'revision_note_updated' => 'Revision note updated.',
    ],


    'provider' => [
        'label' => 'Blog posts',
    ],
];
