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
    'not_local' => 'Importing writes into this site\'s database. It runs only when APP_ENV is "local"; pass --allow-non-local to run it here anyway.',
    'member_required' => 'Name the post\'s author with --as (a member ID or e-mail address).',
    'member_not_found' => 'No member matches ":identifier".',
    'member_inactive' => 'The member ":identifier" is not active.',
    'file_not_found' => 'Not a readable file: :path',
    'front_matter_unclosed' => 'The front matter starts with "---" but has no closing "---" line.',
    'front_matter_line' => 'Front matter line :line cannot be read: :text',
    'front_matter_duplicate' => 'Front matter line :line repeats the key ":key".',
    'unknown_key' => 'Unknown front matter key ":key". Allowed: :keys',
    'seo_not_a_map' => '"seo" must hold indented keys: :keys',
    'not_text' => '":key" must be a single value, not a list.',
    'not_markdown' => 'The post ":slug" is not written in Markdown, so it cannot be updated from a file.',
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
    'stale_revision' => 'The post ":slug" has changed since this file was exported (file revision :revision, post now :current). Export it again and edit that file, or pass --force to overwrite the changes.',
    'changed_in_admin' => 'The post ":slug" was last saved outside the import, for example in the admin. Export it and edit that file, or pass --force to overwrite the changes.',
    'invalid_storage' => '--storage must be one of: :types (given ":storage").',
    'storage_simple_mode' => 'The admin is in simple mode, where a post\'s storage cannot be chosen, so --storage=:storage cannot be honoured. Switch to the advanced mode, or leave --storage out.',
    'would_store' => 'Storage: :storage',
    'stored' => 'Storage: :storage',
];
