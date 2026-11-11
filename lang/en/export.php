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
    'not_local' => 'Exporting is meant for a local site whose content is then deployed. It runs only when APP_ENV is "local"; pass --allow-non-local to run it here anyway.',
    'post_not_found' => 'No post has the slug or ID ":identifier".',
    'not_markdown' => 'The post ":slug" is not written in Markdown, so it cannot be exported as a file.',
    'file_exists' => ':path already exists. Pass --force to overwrite it.',
    'written' => 'Exported post #:id (:slug) to :path.',
];
