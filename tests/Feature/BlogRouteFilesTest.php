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

namespace Plugins\DixlaseBlog\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * routes/web.php holds only the front routes, because DixlaseMultilingual
 * loads it a second time under /{locale}; the admin routes live in
 * routes/admin.php, which core loads once with its admin middleware.
 */
class BlogRouteFilesTest extends TestCase
{
    public function test_web_routes_are_front_routes_only(): void
    {
        $names = $this->namesRegisteredBy('routes/web.php');

        $this->assertContains('dixlase-blog::front.index', $names);
        $this->assertContains('dixlase-blog::front.show', $names);
        $this->assertSame([], array_values(array_filter($names, fn ($n) => ! str_starts_with($n, 'dixlase-blog::front.'))));
    }

    public function test_admin_routes_keep_their_names(): void
    {
        $names = $this->namesRegisteredBy('routes/admin.php');

        foreach ([
            'dixlase-blog::admin.blog.index',
            'dixlase-blog::admin.blog.edit',
            'dixlase-blog::admin.blog.update',
            'dixlase-blog::admin.blog.preview',
            'dixlase-blog::admin.blog.categories.index',
            'dixlase-blog::admin.blog.revisions.restore',
        ] as $name) {
            $this->assertContains($name, $names);
        }
        $this->assertSame([], array_values(array_filter($names, fn ($n) => ! str_starts_with($n, 'dixlase-blog::admin.'))));
    }

    /**
     * @return list<string>
     */
    private function namesRegisteredBy(string $file): array
    {
        $before = array_keys(Route::getRoutes()->getRoutesByName());

        Route::group([], base_path('plugins/DixlaseBlog/'.$file));
        Route::getRoutes()->refreshNameLookups();

        return array_values(array_diff(array_keys(Route::getRoutes()->getRoutesByName()), $before));
    }
}
