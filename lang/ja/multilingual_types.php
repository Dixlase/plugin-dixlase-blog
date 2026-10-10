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

// Human-readable labels for the translatable types this plugin exposes
// via the multilingual-content capability. The DixlaseMultilingual
// editor resolves "<plugin-slug>::multilingual_types.<type>" for each
// declared type key, falling back to the raw "dixlase-blog:post" key
// when no entry exists here.
return [
    'post' => '記事',
];
