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

namespace Plugins\DixlaseBlog\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards that the front stylesheet changes layout at Tailwind's breakpoints
 * ($bp-sm … $bp-2xl), where the theme does, not at ad-hoc pixel widths.
 *
 * Pure file reads: no framework boot, no database.
 */
class StyleBreakpointsTest extends TestCase
{
    public function test_every_width_media_query_uses_a_breakpoint_variable(): void
    {
        $scss = (string) file_get_contents(__DIR__.'/../../resources/src/css/style.scss');
        preg_match_all('/@media\s*(.+?)\s*\{\s*$/m', $scss, $matches);

        $offenders = array_filter(
            array_map('trim', $matches[1]),
            fn (string $query): bool => str_contains($query, 'width') && ! str_contains($query, '$bp-'),
        );

        $this->assertSame([], array_values($offenders), 'Use $bp-sm, $bp-md, $bp-lg, $bp-xl or $bp-2xl.');
    }
}
