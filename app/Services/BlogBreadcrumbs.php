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

use Illuminate\Support\Carbon;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;

/**
 * The breadcrumb trail of each public blog page: Home > Blog > ... > the
 * page itself. Each item is ['label', 'url', 'lang']; the last one is the
 * current page.
 */
class BlogBreadcrumbs
{
    /** Guard against a broken parent chain. */
    private const MAX_CATEGORY_DEPTH = 20;

    /**
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    public function forIndex(): array
    {
        return $this->base();
    }

    /**
     * Through the post's first category and its parents, when it has one.
     *
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    public function forPost(BlogPost $post, string $url): array
    {
        $category = $post->categories->sortBy('display_order')->first();
        $trail = $category ? $this->categoryTrail($category) : [];

        return [
            ...$this->base(),
            ...$trail,
            $this->item((string) $post->localized('title'), $url, $post->localizedLang('title')),
        ];
    }

    /**
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    public function forCategory(BlogCategory $category): array
    {
        return [...$this->base(), ...$this->categoryTrail($category)];
    }

    /**
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    public function forTag(BlogTag $tag): array
    {
        return [
            ...$this->base(),
            $this->item(
                '#'.$tag->localized('name'),
                route('dixlase-blog::front.tag', ['slug' => $tag->slug]),
                $tag->localizedLang('name'),
            ),
        ];
    }

    /**
     * A month, or a day under its month.
     *
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    public function forDate(Carbon $start, bool $isDay): array
    {
        $locale = app()->getLocale();
        $month = $this->item(
            $start->copy()->locale($locale)->isoFormat(__('dixlase-blog::front.archive.month_format')),
            route('dixlase-blog::front.month', ['year' => $start->year, 'month' => $start->format('m')]),
            $locale,
        );

        if (! $isDay) {
            return [...$this->base(), $month];
        }

        return [
            ...$this->base(),
            $month,
            $this->item(
                $start->copy()->locale($locale)->isoFormat(__('dixlase-blog::front.archive.day_format')),
                route('dixlase-blog::front.day', ['year' => $start->year, 'month' => $start->format('m'), 'day' => $start->format('d')]),
                $locale,
            ),
        ];
    }

    /**
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    public function forSearch(string $url): array
    {
        return [...$this->base(), $this->item(__('dixlase-blog::front.archive.search_kicker'), $url, app()->getLocale())];
    }

    /**
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    private function base(): array
    {
        $locale = app()->getLocale();

        return [
            $this->item(__('dixlase-blog::front.breadcrumbs.home'), url('/'), $locale),
            $this->item(__('dixlase-blog::front.index.heading'), route('dixlase-blog::front.index'), $locale),
        ];
    }

    /**
     * The category under its parents, top first.
     *
     * @return list<array{label: string, url: string, lang: string|null}>
     */
    private function categoryTrail(BlogCategory $category): array
    {
        $chain = [];
        for ($current = $category, $depth = 0; $current !== null && $depth < self::MAX_CATEGORY_DEPTH; $depth++) {
            array_unshift($chain, $this->item(
                (string) $current->localized('label'),
                route('dixlase-blog::front.category', ['slug' => $current->slug]),
                $current->localizedLang('label'),
            ));
            $current = $current->parent;
        }

        return $chain;
    }

    /**
     * @return array{label: string, url: string, lang: string|null}
     */
    private function item(string $label, string $url, ?string $lang): array
    {
        return ['label' => $label, 'url' => $url, 'lang' => $lang];
    }
}
