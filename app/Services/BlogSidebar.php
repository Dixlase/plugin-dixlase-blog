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

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

/**
 * Data for the blog sidebar, built from the settings: which widgets show,
 * in which order, and what each one lists. Only visible posts count.
 * Each widget's template is dixlase-blog::front.sidebar.<key>.
 */
class BlogSidebar
{
    /** Most tags the tag widget lists, by post count. */
    public const TAG_LIMIT = 30;

    /** @var Collection<int, Carbon>|null Publish dates of the visible posts. */
    private ?Collection $dates = null;

    public function __construct(private readonly BlogSettings $settings)
    {
    }

    /**
     * @return array{enabled: bool, position: string, widgets: list<array{key: string, data: array<string, mixed>}>}
     */
    public function build(Request $request): array
    {
        if (! $this->settings->sidebarEnabled()) {
            return ['enabled' => false, 'position' => $this->settings->sidebarPosition(), 'widgets' => []];
        }

        $widgets = [];
        foreach ($this->settings->sidebarWidgets() as $widget) {
            if ($widget['enabled']) {
                $widgets[] = ['key' => $widget['key'], 'data' => $this->dataFor($widget['key'], $request)];
            }
        }

        return [
            'enabled' => $widgets !== [],
            'position' => $this->settings->sidebarPosition(),
            'widgets' => $widgets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFor(string $key, Request $request): array
    {
        return match ($key) {
            'search' => ['query' => (string) $request->query('q', '')],
            'categories' => ['categories' => $this->categoryTree()],
            'recent' => ['posts' => $this->recentPosts()],
            'archives' => ['months' => $this->months()],
            'calendar' => $this->calendar($this->calendarMonth($request)),
            'tags' => ['tags' => $this->tags()],
            default => [],
        };
    }

    /**
     * Categories with visible posts, or with descendants that have some,
     * nested: each item is ['category' => BlogCategory, 'count' => int,
     * 'children' => list<item>].
     *
     * @return list<array{category: BlogCategory, count: int, children: list<mixed>}>
     */
    private function categoryTree(): array
    {
        $categories = BlogCategory::query()
            ->withCount(['posts' => fn ($query) => $query->currentlyVisible()])
            ->orderBy('display_order')
            ->orderBy('label')
            ->get();

        $byParent = $categories->groupBy(fn (BlogCategory $category) => (int) $category->parent_id);

        $build = function (int $parentId) use (&$build, $byParent): array {
            $items = [];
            foreach ($byParent->get($parentId, collect()) as $category) {
                $children = $build((int) $category->id);
                if ($category->posts_count > 0 || $children !== []) {
                    $items[] = ['category' => $category, 'count' => (int) $category->posts_count, 'children' => $children];
                }
            }

            return $items;
        };

        return $build(0);
    }

    /**
     * @return Collection<int, BlogPost>
     */
    private function recentPosts(): Collection
    {
        return BlogPost::query()
            ->currentlyVisible()
            ->orderByDesc('published_at')
            ->limit($this->settings->sidebarRecentCount())
            ->get();
    }

    /**
     * Months with visible posts, newest first.
     *
     * @return list<array{year: int, month: int, count: int}>
     */
    private function months(): array
    {
        return $this->dates()
            ->countBy(fn (Carbon $date) => $date->format('Y-m'))
            ->sortKeysDesc()
            ->map(fn (int $count, string $ym) => [
                'year' => (int) substr($ym, 0, 4),
                'month' => (int) substr($ym, 5, 2),
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * The month a date archive shows, else the current month.
     */
    private function calendarMonth(Request $request): Carbon
    {
        $year = $request->route('year');
        $month = $request->route('month');

        if (is_numeric($year) && is_numeric($month) && checkdate((int) $month, 1, (int) $year)) {
            return Carbon::create((int) $year, (int) $month, 1)->startOfDay();
        }

        return Carbon::now()->startOfMonth();
    }

    /**
     * A month as weeks of seven cells from Sunday; a cell is null outside
     * the month, else the day and whether it has visible posts. Previous
     * and next point at the nearest months that have posts.
     *
     * @return array{month: Carbon, weeks: list<list<array{day: int, hasPosts: bool}|null>>, previous: Carbon|null, next: Carbon|null}
     */
    private function calendar(Carbon $month): array
    {
        $postDays = $this->dates()
            ->filter(fn (Carbon $date) => $date->year === $month->year && $date->month === $month->month)
            ->map(fn (Carbon $date) => $date->day)
            ->unique()
            ->flip();

        $cells = array_fill(0, $month->dayOfWeek, null);
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $cells[] = ['day' => $day, 'hasPosts' => $postDays->has($day)];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        $monthStarts = $this->dates()->map(fn (Carbon $date) => $date->copy()->startOfMonth());

        return [
            'month' => $month,
            'weeks' => array_chunk($cells, 7),
            'previous' => $monthStarts->filter(fn (Carbon $start) => $start->lt($month))->sortDesc()->first(),
            'next' => $monthStarts->filter(fn (Carbon $start) => $start->gt($month))->sort()->first(),
        ];
    }

    /**
     * Tags with visible posts, most used first.
     *
     * @return Collection<int, BlogTag>
     */
    private function tags(): Collection
    {
        return BlogTag::query()
            ->withCount(['posts' => fn ($query) => $query->currentlyVisible()])
            ->get()
            ->filter(fn (BlogTag $tag) => $tag->posts_count > 0)
            ->sortBy([['posts_count', 'desc'], ['name', 'asc']])
            ->take(self::TAG_LIMIT)
            ->values();
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function dates(): Collection
    {
        return $this->dates ??= BlogPost::query()
            ->currentlyVisible()
            ->pluck('published_at')
            ->map(fn ($date) => Carbon::parse($date));
    }
}
