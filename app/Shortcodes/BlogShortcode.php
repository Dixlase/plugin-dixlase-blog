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

namespace Plugins\DixlaseBlog\App\Shortcodes;

use Illuminate\Database\Eloquent\Builder;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogFullPosts;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Throwable;

/**
 * [blog] -- the latest posts, in the blog top page's order, wherever content
 * goes through core's shortcode parser (the front page, for one).
 *
 *   [blog count="3" layout="cards|list|full" category="slug" tag="slug" link="true|false"]
 *
 * Renders nothing when there are no posts, and nothing (the error is
 * reported) when rendering fails, so a visitor never sees an error.
 */
class BlogShortcode
{
    public const COUNT_DEFAULT = 3;

    public const COUNT_MAX = 50;

    /** cards: the blog's cards; list: date and title; full: each post in full. */
    public const LAYOUTS = ['cards', 'list', 'full'];

    public function __construct(
        private readonly BlogFullPosts $fullPosts,
        private readonly BlogSettings $settings,
    ) {
    }

    /**
     * @param  array<int|string, string>  $attributes
     */
    public function render(array $attributes = [], ?string $content = null): string
    {
        try {
            $layout = $this->layout($attributes['layout'] ?? null);
            $posts = $this->query($attributes)->limit($this->count($attributes['count'] ?? null))->get();

            if ($posts->isEmpty()) {
                return '';
            }

            return view('dixlase-blog::front.shortcode', [
                'posts' => $posts,
                'layout' => $layout,
                'fullPosts' => $layout === 'full' ? $posts->map(fn (BlogPost $post) => $this->fullPosts->for($post))->all() : [],
                // What posts show beside the title, as on the blog's own pages.
                'postFields' => $this->settings->postFields(),
                'showLink' => $this->flag($attributes['link'] ?? null, true),
            ])->render();
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * @param  array<int|string, string>  $attributes
     * @return Builder<BlogPost>
     */
    private function query(array $attributes): Builder
    {
        // The top page's order: pinned posts first, then the newest.
        $query = BlogPost::query()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->withPublishedCommentCount()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        $category = trim((string) ($attributes['category'] ?? ''));
        if ($category !== '') {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $category));
        }

        $tag = trim((string) ($attributes['tag'] ?? ''));
        if ($tag !== '') {
            $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $tag));
        }

        return $query;
    }

    private function count(?string $value): int
    {
        $count = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::COUNT_MAX]]);

        return $count === false ? self::COUNT_DEFAULT : $count;
    }

    private function layout(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, self::LAYOUTS, true) ? $value : 'cards';
    }

    /** true / false, also yes / no, on / off, 1 / 0 and show / hide. */
    private function flag(?string $value, bool $default): bool
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            // filter_var() reads an empty string as false, not as absent.
            '' => $default,
            'show' => true,
            'hide' => false,
            default => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default,
        };
    }
}
