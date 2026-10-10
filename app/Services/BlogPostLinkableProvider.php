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

use App\Contracts\PluginIntegration\LinkableProviderInterface;
use App\DTO\PluginIntegration\LinkableDTO;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * Lists published posts for link pickers and for DixlaseSEO's sitemap.
 *
 * One item per post with the locale-less URL: the sitemap adds the
 * /{locale} variants and their hreflang links itself, and the post's
 * slug is shared by every language.
 */
final class BlogPostLinkableProvider implements LinkableProviderInterface
{
    public function getProviderKey(): string
    {
        return BlogPost::PLUGIN_SLUG;
    }

    public function getProviderLabel(): string
    {
        return __('dixlase-blog::admin.provider.label');
    }

    public function getProviderIcon(): ?string
    {
        return 'fas fa-blog';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAvailableItems(int $limit = 100): array
    {
        return BlogPost::query()
            ->currentlyVisible()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (BlogPost $post) => $this->toDTO($post))
            ->all();
    }

    public function searchItems(string $query, int $limit = 20): array
    {
        $like = '%'.addcslashes($query, '%_\\').'%';

        return BlogPost::query()
            ->currentlyVisible()
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('slug', 'like', $like))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (BlogPost $post) => $this->toDTO($post))
            ->all();
    }

    public function getItemById(string $id): ?LinkableDTO
    {
        $post = ctype_digit($id) ? BlogPost::query()->currentlyVisible()->find((int) $id) : null;

        return $post !== null ? $this->toDTO($post) : null;
    }

    private function toDTO(BlogPost $post): LinkableDTO
    {
        return new LinkableDTO(
            id: (string) $post->id,
            title: (string) ($post->title ?: $post->slug),
            url: '/blog/'.$post->slug,
            type: 'post',
            source: BlogPost::PLUGIN_SLUG,
            sourceTable: $post->getTable(),
            locale: $post->sourceLocale(),
            meta: ['updated_at' => $post->updated_at?->toIso8601String()],
        );
    }
}
