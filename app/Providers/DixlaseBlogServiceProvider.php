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

namespace Plugins\DixlaseBlog\App\Providers;

use App\Contracts\Repositories\ThemeRepositoryInterface;
use App\Traits\PluginLoaderTrait;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class DixlaseBlogServiceProvider extends ServiceProvider
{
    use PluginLoaderTrait;

    public function register(): void
    {
        $this->mergeAdminNavConfig(__DIR__.'/../../config/admin.php');
    }

    public function boot(): void
    {
        // Theme override: register the active theme's blog views first so
        // they take precedence over the plugin's defaults. Documented
        // contract for theme authors: drop a file at
        // themes/<theme>/views/dixlase-blog/front/<view>.blade.php to
        // override the matching plugin view.
        $this->registerThemeViewOverrides();

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'dixlase-blog');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'dixlase-blog');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Register the active theme's view-override path under the
     * dixlase-blog namespace, so themes can override blog views by
     * placing files at themes/<theme>/views/dixlase-blog/.
     *
     * Order matters: this method runs *before* loadViewsFrom() so that
     * Laravel's view finder iterates the theme path first when looking
     * up a "dixlase-blog::..." view. The plugin's bundled views become
     * the fallback.
     *
     * Catches Throwable because the theme repository reads from the
     * database; during install / migrate the theme_settings table may
     * not exist yet.
     */
    private function registerThemeViewOverrides(): void
    {
        try {
            $themeRepository = app(ThemeRepositoryInterface::class);
            $themeDirectory = $themeRepository->getEnabledThemeDirectory();
            if (empty($themeDirectory)) {
                return;
            }
            $themeViewPath = base_path('themes/'.$themeDirectory.'/views/dixlase-blog');
            if (is_dir($themeViewPath)) {
                View::addNamespace('dixlase-blog', $themeViewPath);
            }
        } catch (Throwable) {
            // Database not ready (install / migrations) — silently skip.
        }
    }
}
