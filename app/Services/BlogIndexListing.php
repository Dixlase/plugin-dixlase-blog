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

namespace Plugins\DixlaseBlog\App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

/**
 * The blog's top-page listing, independently of the route.
 *
 * This was the body of DixlaseBlogFrontController::index(). It is a class so
 * that something other than that route can ask for the same listing — the
 * front-page body provider renders the blog top inside a theme's layout, and
 * it must honour exactly the settings the blog's own page does (#257).
 *
 * Every decision about the listing stays here: the layout, whether the full
 * text is included, how many posts a page holds, and which fields a card
 * shows. A caller supplies only the page number.
 */
final class BlogIndexListing
{
    public function __construct(
        private readonly BlogSettings $settings,
        private readonly BlogFullPosts $fullPosts,
    ) {}

    /**
     * The posts for one page of the top-page listing.
     *
     * @param  ?int  $page  Null resolves the page from the current request,
     *                      which is what the blog's own route wants. A caller
     *                      rendering inside another page passes it explicitly,
     *                      because the request it is answering is that page's.
     * @return array{
     *     posts: LengthAwarePaginator,
     *     fullPosts: ?array<int, array{post: BlogPost, contentHtml: ?string, customCssUrl: ?string}>,
     *     postFields: array<int, string>
     * }
     */
    public function for(?int $page = null): array
    {
        $full = $this->settings->indexLayout() === 'full';
        $withBody = $this->settings->latestBody() === 'full';

        // Pinned posts first; the archives and the feed keep date order.
        $posts = BlogPost::query()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->withPublishedCommentCount()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate($this->settings->postsPerPage(), ['*'], 'page', $page);

        return [
            'posts' => $posts,
            // The latest posts: each with its excerpt or its full text.
            'fullPosts' => $full
                ? $posts->getCollection()->map(fn (BlogPost $post) => $this->fullPosts->for($post, $withBody))->all()
                : null,
            'postFields' => $this->settings->postFields(),
        ];
    }
}
