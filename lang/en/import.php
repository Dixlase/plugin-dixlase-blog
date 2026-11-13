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
    'not_local' => 'This site is not local (APP_ENV is ":env"). Importing writes into its database. Pass --force to run without asking.',
    'member_required' => 'Name the post\'s author with --as (the member\'s account name or e-mail address).',
    'member_not_found' => 'No member has the account name or e-mail address ":identifier". --as takes one of those, not a member ID.',
    'member_inactive' => 'The member ":identifier" is not active.',
    'file_not_found' => 'Not a readable file: :path',
    'front_matter_unclosed' => 'The front matter starts with "---" but has no closing "---" line.',
    'front_matter_line' => 'Front matter line :line cannot be read: :text',
    'front_matter_duplicate' => 'Front matter line :line repeats the key ":key".',
    'unknown_key' => 'Unknown front matter key ":key". Allowed: :keys',
    'seo_not_a_map' => '"seo" must hold indented keys: :keys',
    'not_text' => '":key" must be a single value, not a list.',
    'invalid_editor' => '"editor" must be one of: :editors (given ":editor").',
    'editor_not_importable' => 'The post ":slug" uses the :editor editor, which has no text body to update from a file. Only Markdown and HTML posts can be.',
    'editor_fixed' => 'The post ":slug" is written in :editor, and a saved post\'s editor cannot change, so "editor: :named" cannot be honoured. Leave "editor" out, or write it as :editor.',
    'html_not_allowed' => 'The post ":slug" is an HTML post, which only a member who may author scripts (admin and above) can change.',
    'custom_js_html_only' => '"custom_js" is only for HTML posts; a Markdown post never runs a script.',
    'custom_assets_not_allowed' => 'Only a member who may author scripts (admin and above) can set :keys.',
    'asset_must_be_local' => ':key must be a path to a local file, relative to the post file.',
    'asset_not_found' => ':key: file not found: :path',
    'live_post' => 'The post ":slug" is published or scheduled, and the author\'s role cannot publish, so it cannot be changed.',
    'unknown_categories' => 'No category has the slug: :slugs',
    'image_not_found' => ':field: image not found: :path',
    'image_must_be_local' => ':field must be a path to a local image file.',
    'image_failed' => ':field: :path could not be imported: :error',
    'failed' => 'Nothing was saved.',
    'would_create' => 'Would create a new post.',
    'would_update' => 'Would update post #:id (:slug).',
    'would_import' => 'Would import :count image(s):',
    'created' => 'Created post #:id (:slug) as :status.',
    'updated' => 'Updated post #:id (:slug); it is :status.',
    'images' => 'Images: :imported imported, :reused already in the library.',
    'stale_revision' => 'The post ":slug" has changed since this file was exported (file revision :revision, post now :current). Export it again and edit that file, or pass --overwrite to overwrite the changes.',
    'changed_in_admin' => 'The post ":slug" was last saved outside the import, for example in the admin. Export it and edit that file, or pass --overwrite to overwrite the changes.',
    'draft_live_post' => 'The post ":slug" is published or scheduled. --draft would take it off the site, so it was left alone; unpublish it in the admin first, or import without --draft.',
    'would_status' => 'Status: :status',
    'no_markdown_files' => 'No .md files in the directory :path',
    'summary' => 'Done: :ok imported, :failed failed.',
    'summary_dry_run' => 'Checked: :ok would be imported, :failed would fail.',
    'invalid_storage' => '--storage must be one of: :types (given ":storage").',
    'storage_simple_mode' => 'The admin is in simple mode, where a post\'s storage cannot be chosen, so --storage=:storage cannot be honoured. Switch to the advanced mode, or leave --storage out.',
    'would_store' => 'Storage: :storage',
    'stored' => 'Storage: :storage',
];
