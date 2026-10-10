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

use App\Contracts\PluginIntegration\SeoMetaProviderInterface;
use App\Contracts\Repositories\MediaRepositoryInterface;
use App\DTO\PluginIntegration\SeoMetaDTO;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * A post's SEO description and OGP image, stored by DixlaseSEO through the
 * core SeoMetaProviderInterface contract. Everything here is a no-op when
 * DixlaseSEO is not enabled or has the blog's integration switched off.
 */
final class BlogSeoMeta
{
    public function enabled(): bool
    {
        return $this->provider() !== null;
    }

    /**
     * View data for the post form's SEO section.
     *
     * @return array{seoMetaEnabled: bool, seoMeta: SeoMetaDTO|null, seoOgpMedia: mixed}
     */
    public function formData(BlogPost $post): array
    {
        $provider = $this->provider();
        $meta = $provider !== null && $post->exists
            ? $provider->getMeta(BlogPost::PLUGIN_SLUG, (string) $post->id)
            : null;

        return [
            'seoMetaEnabled' => $provider !== null,
            'seoMeta' => $meta,
            'seoOgpMedia' => $meta?->ogpMediaId !== null
                ? app(MediaRepositoryInterface::class)->find($meta->ogpMediaId)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(BlogPost $post, array $validated): void
    {
        $provider = $this->provider();
        if ($provider === null || ! array_key_exists('seo_meta', $validated)) {
            return;
        }

        $input = (array) ($validated['seo_meta'] ?? []);
        $provider->saveMeta(BlogPost::PLUGIN_SLUG, (string) $post->id, new SeoMetaDTO(
            description: filled($input['description'] ?? null) ? (string) $input['description'] : null,
            ogpMediaId: isset($input['ogp_media_id']) ? (int) $input['ogp_media_id'] : null,
        ));
    }

    /**
     * The stored description, in the post's source language.
     */
    public function description(BlogPost $post): ?string
    {
        $description = $this->provider()?->getMeta(BlogPost::PLUGIN_SLUG, (string) $post->id)?->description;

        return filled($description) ? $description : null;
    }

    /**
     * Remove the stored meta. Runs when a post is deleted for good, even if
     * the integration is switched off, so no orphan row is left behind.
     */
    public function delete(int $postId): void
    {
        if (app()->has(SeoMetaProviderInterface::class)) {
            app(SeoMetaProviderInterface::class)->deleteMeta(BlogPost::PLUGIN_SLUG, (string) $postId);
        }
    }

    private function provider(): ?SeoMetaProviderInterface
    {
        if (! app()->has(SeoMetaProviderInterface::class)) {
            return null;
        }

        $provider = app(SeoMetaProviderInterface::class);

        return $provider->isEnabledForPlugin(BlogPost::PLUGIN_SLUG) ? $provider : null;
    }
}
