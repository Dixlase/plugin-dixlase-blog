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

use App\Contracts\TranslationResolver;
use Illuminate\Support\Facades\App;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * Changing the language a saved post is written in (#133)
 *
 * The post's own text stays where it is; only the language labels move.
 * The usual reason to change it is a post saved under the wrong language,
 * whose text is already in the language being chosen. A translation filed
 * under that language is then in the old one, so it moves there.
 *
 * Translations are reached through core's TranslationResolver contract,
 * so nothing here depends on which extension stores them.
 */
final class BlogPostSourceLanguage
{
    /**
     * The fields plugin.json declares translatable for dixlase-blog:post.
     */
    public const TRANSLATABLE_FIELDS = [
        'title',
        'excerpt',
        'content',
        'seo_description',
        'featured_media_id',
        'seo_ogp_media_id',
    ];

    /**
     * Whether the post has a translation of any field in the locale,
     * published or not.
     */
    public function hasTranslation(BlogPost $post, string $locale): bool
    {
        $resolver = $this->resolver();
        if ($resolver === null || ! $post->exists) {
            return false;
        }

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            if ($resolver->exists($post, $field, $locale)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The locales, out of the given ones, in which the post has a translation.
     *
     * @param  list<string>  $locales
     * @return list<string>
     */
    public function translatedLocales(BlogPost $post, array $locales): array
    {
        return array_values(array_filter($locales, fn (string $locale): bool => $this->hasTranslation($post, $locale)));
    }

    /**
     * Move the translation filed under the new source language to the old
     * one. Without a translation in the new language nothing moves, and the
     * old language is left with no text of its own.
     */
    public function move(BlogPost $post, string $from, string $to): void
    {
        $resolver = $this->resolver();
        if ($resolver === null || $from === $to) {
            return;
        }

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            if (! $resolver->exists($post, $field, $to)) {
                continue;
            }

            // all() includes a translation that is not published yet,
            // which resolve() hides.
            $value = $resolver->all($post, $field)[$to] ?? null;
            $resolver->store($post, $field, $value, $from);
            $resolver->delete($post, $field, $to);
        }
    }

    private function resolver(): ?TranslationResolver
    {
        return App::bound(TranslationResolver::class) ? App::make(TranslationResolver::class) : null;
    }
}
