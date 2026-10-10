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

/**
 * Derives a plain-text excerpt from post content.
 *
 * Used when a post is saved without an excerpt, and on the front when a
 * translation carries content but no excerpt of its own.
 */
final class BlogExcerpt
{
    /** Excerpt length when generating from content (matches X / Twitter). */
    public const AUTO_LENGTH = 140;

    public static function fromContent(?string $content): ?string
    {
        if (blank($content)) {
            return null;
        }

        $plain = $content;
        // Strip the most common Markdown markup so the auto-excerpt does not
        // surface raw "**bold**" / "## heading" syntax in OG descriptions.
        $plain = preg_replace('/```[\s\S]*?```/', ' ', $plain) ?? $plain;
        $plain = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $plain) ?? $plain;
        $plain = preg_replace('/[*_]{1,3}([^*_]+?)[*_]{1,3}/', '$1', $plain) ?? $plain;
        $plain = preg_replace('/^#+\s*/m', '', $plain) ?? $plain;
        $plain = preg_replace('/`([^`]+)`/', '$1', $plain) ?? $plain;
        $plain = strip_tags($plain);
        $plain = trim((string) preg_replace('/\s+/', ' ', $plain));

        if ($plain === '') {
            return null;
        }

        return mb_substr($plain, 0, self::AUTO_LENGTH);
    }
}
