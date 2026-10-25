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
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Plugins\DixlaseBlog\App\Services;

use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * A post as a listing shows it in full: the blog's top page in its "in full"
 * layout, and the [blog] shortcode with layout="full".
 */
class BlogFullPosts
{
    public function __construct(private readonly BlogContentRenderer $renderer)
    {
    }

    /**
     * The rendered body, and the URL of the post's own CSS. Its JavaScript
     * is left out: a post's script assumes it is alone on the page.
     *
     * @return array{post: BlogPost, contentHtml: string, customCssUrl: ?string}
     */
    public function for(BlogPost $post): array
    {
        $hasCss = BlogScriptAuthoringPolicy::carriesCustomCss($post->editor_type) && trim((string) $post->custom_css) !== '';

        return [
            'post' => $post,
            'contentHtml' => $this->renderer->renderPost($post),
            'customCssUrl' => $hasCss
                ? route('dixlase-blog::front.custom-style', ['slug' => $post->slug]).'?v='.($post->updated_at?->timestamp ?? 0)
                : null,
        ];
    }
}
