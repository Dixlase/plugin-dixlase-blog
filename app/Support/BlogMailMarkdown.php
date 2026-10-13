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
 * Neutralise Markdown in visitor-supplied text before it goes into a
 * Markdown mail. Blade's {{ }} escapes HTML, but Markdown passes through,
 * so a commenter could otherwise have the site's own mailer deliver a
 * formatted link to the site's staff. The characters that build links,
 * images, emphasis, code and headings are backslash-escaped.
 */
final class BlogMailMarkdown
{
    public static function escape(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return (string) preg_replace('/([\\\\`*_{}\[\]()!#|~])/', '\\\\$1', $text);
    }
}
