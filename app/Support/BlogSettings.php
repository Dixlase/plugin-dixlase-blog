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

namespace Plugins\DixlaseBlog\App\Support;

use Plugins\DixlaseBlog\App\Models\BlogSetting;

/**
 * The blog's settings with their defaults and types. Values are read once
 * per request and kept on this singleton.
 */
class BlogSettings
{
    public const POSTS_PER_PAGE_MIN = 1;

    public const POSTS_PER_PAGE_MAX = 100;

    /**
     * Every setting and its default, as stored (strings). Core inserts the
     * missing rows on install and update; see DixlaseBlogServiceProvider.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'posts_per_page' => '12',
    ];

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    /**
     * Posts per page on the index and the archives.
     */
    public function postsPerPage(): int
    {
        $value = (int) $this->value('posts_per_page');

        return max(self::POSTS_PER_PAGE_MIN, min(self::POSTS_PER_PAGE_MAX, $value));
    }

    /**
     * Store settings and forget the values read so far.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function save(array $values): void
    {
        $known = array_intersect_key($values, self::DEFAULTS);
        BlogSetting::store(array_map(
            fn ($value) => is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string) $value),
            $known,
        ));
        $this->values = null;
    }

    /**
     * Every setting as stored, defaults filled in.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->values ??= BlogSetting::valuesFor(array_keys(self::DEFAULTS)) + self::DEFAULTS;
    }

    private function value(string $name): ?string
    {
        return $this->all()[$name] ?? self::DEFAULTS[$name] ?? null;
    }
}
