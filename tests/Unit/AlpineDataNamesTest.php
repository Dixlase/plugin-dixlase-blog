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
 * Guards that the blog's views name their Alpine components and pass values
 * through data-* attributes. Dixlase will switch to the CSP build of Alpine
 * (@alpinejs/csp), which evaluates no JavaScript in an x-data expression:
 * an object literal or a call with arguments stops working there.
 *
 * Pure file reads: no framework boot, no database.
 */
class AlpineDataNamesTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../resources/views';

    public function test_every_x_data_is_a_registered_name(): void
    {
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::VIEWS, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all('/\bx-data="([^"]*)"/', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $expression) {
                if (preg_match('/^[A-Za-z_$][\w$]*$/', trim($expression)) !== 1) {
                    $offenders[] = substr($file->getPathname(), strlen(self::VIEWS) + 1).': x-data="'.trim($expression).'"';
                }
            }
        }

        $this->assertSame([], $offenders, "Register the component with Alpine.data() and use its bare name:\n".implode("\n", $offenders));
    }
}
