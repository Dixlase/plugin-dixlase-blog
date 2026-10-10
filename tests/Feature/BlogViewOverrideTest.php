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

use App\Contracts\Repositories\ThemeRepositoryInterface;
use Illuminate\Support\Facades\File;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Tests\TestCase;

/**
 * Blog views resolve in the same order as DixlasePages: the site's
 * custom/ directory, then the active theme, then the plugin's defaults.
 * Both override roots live in a throwaway directory, never in the real
 * custom/ or themes/.
 */
class BlogViewOverrideTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = 'storage/framework/testing/blog-views-'.uniqid();
        config([
            'custom.custom_files_dir' => $this->root.'/custom',
            'themes.theme_directory' => $this->root.'/themes',
        ]);

        $theme = $this->createStub(ThemeRepositoryInterface::class);
        $theme->method('getEnabledThemeDirectory')->willReturn('TestTheme');
        $this->app->instance(ThemeRepositoryInterface::class, $theme);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));

        parent::tearDown();
    }

    public function test_override_paths_put_custom_before_the_theme(): void
    {
        $this->assertSame([
            base_path($this->root.'/custom/plugins/DixlaseBlog/resources/views'),
            base_path($this->root.'/themes/TestTheme/plugins/DixlaseBlog/resources/views'),
        ], $this->provider()->overridePaths());
    }

    public function test_plugin_view_is_used_without_overrides(): void
    {
        $this->registerViews();

        $this->assertSame(
            realpath(base_path('plugins/DixlaseBlog/resources/views/front/_pagination.blade.php')),
            realpath(view()->getFinder()->find('dixlase-blog::front._pagination')),
        );
    }

    public function test_theme_view_overrides_the_plugin_view(): void
    {
        $this->writeOverride('themes/TestTheme', 'theme card');
        $this->registerViews();

        $this->assertSame('theme card', trim(view('dixlase-blog::front._card')->render()));
    }

    public function test_custom_view_overrides_the_theme_view(): void
    {
        $this->writeOverride('themes/TestTheme', 'theme card');
        $this->writeOverride('custom', 'custom card');
        $this->registerViews();

        $this->assertSame('custom card', trim(view('dixlase-blog::front._card')->render()));
    }

    private function writeOverride(string $base, string $body): void
    {
        $path = base_path($this->root.'/'.$base.'/plugins/DixlaseBlog/resources/views/front/_card.blade.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $body);
    }

    private function registerViews(): void
    {
        // Start from a clean namespace so only this registration counts.
        view()->getFinder()->replaceNamespace('dixlase-blog', []);
        view()->getFinder()->flush();
        $this->provider()->registerViews();
    }

    private function provider(): DixlaseBlogServiceProvider
    {
        return new DixlaseBlogServiceProvider($this->app);
    }
}
