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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * The posts shown around a post: the previous and next visible posts by
 * publish date, and related posts that share a category or a tag.
 */
class BlogPostNeighbours
{
    /** Related posts shown under a post. */
    public const RELATED_LIMIT = 3;

    /** Candidates ranked for relatedness, newest first. */
    private const RELATED_CANDIDATES = 50;

    /**
     * The visible post published just before this one. Posts published at
     * the same moment are ordered by id. A post without a publish date (a
     * draft, in the preview) has no place in the timeline and so has no
     * neighbours.
     */
    public function previous(BlogPost $post): ?BlogPost
    {
        if ($post->published_at === null) {
            return null;
        }

        return BlogPost::query()
            ->currentlyVisible()
            ->where(fn (Builder $query) => $query
                ->where('published_at', '<', $post->published_at)
                ->orWhere(fn (Builder $same) => $same
                    ->where('published_at', $post->published_at)
                    ->where('id', '<', $post->id)))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    public function next(BlogPost $post): ?BlogPost
    {
        if ($post->published_at === null) {
            return null;
        }

        return BlogPost::query()
            ->currentlyVisible()
            ->where(fn (Builder $query) => $query
                ->where('published_at', '>', $post->published_at)
                ->orWhere(fn (Builder $same) => $same
                    ->where('published_at', $post->published_at)
                    ->where('id', '>', $post->id)))
            ->orderBy('published_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Visible posts sharing a category or a tag with this one: the most
     * shared first, then the newest.
     *
     * @return Collection<int, BlogPost>
     */
    public function related(BlogPost $post): Collection
    {
        $categoryIds = $post->categories->pluck('id')->all();
        $tagIds = $post->tags->pluck('id')->all();
        if ($categoryIds === [] && $tagIds === []) {
            return collect();
        }

        $inCategories = fn (Builder $query) => $query->whereIn('plg_dixlase_blog_categories.id', $categoryIds);
        $inTags = fn (Builder $query) => $query->whereIn('plg_dixlase_blog_tags.id', $tagIds);

        return BlogPost::query()
            ->currentlyVisible()
            ->whereKeyNot($post->id)
            ->where(fn (Builder $query) => $query
                ->whereHas('categories', $inCategories)
                ->orWhereHas('tags', $inTags))
            ->withCount(['categories as shared_categories' => $inCategories, 'tags as shared_tags' => $inTags])
            ->with(['featuredMedia'])
            ->orderByDesc('published_at')
            ->limit(self::RELATED_CANDIDATES)
            ->get()
            ->sortBy([
                fn (BlogPost $a, BlogPost $b) => ($b->shared_categories + $b->shared_tags) <=> ($a->shared_categories + $a->shared_tags),
                fn (BlogPost $a, BlogPost $b) => $b->published_at <=> $a->published_at,
            ])
            ->take(self::RELATED_LIMIT)
            ->values();
    }
}
