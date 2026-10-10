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
use Plugins\DixlaseBlog\App\Services\BlogLocalizedUrlProvider;
use Plugins\DixlaseBlog\App\Services\BlogPostLinkableProvider;
use Plugins\DixlaseBlog\App\Services\BlogSeoMeta;
use Throwable;

class DixlaseBlogServiceProvider extends ServiceProvider
{
    use PluginLoaderTrait;

    public function register(): void
    {
        $this->mergeAdminNavConfig(__DIR__.'/../../config/admin.php');

        // Picked up by DixlaseSEO's sitemap and by link pickers.
        $this->app->singleton(BlogPostLinkableProvider::class);
        $this->app->tag([BlogPostLinkableProvider::class], 'linkable.providers');

        // Picked up by DixlaseMultilingual for hreflang alternates.
        $this->app->singleton(BlogLocalizedUrlProvider::class);
        $this->app->tag([BlogLocalizedUrlProvider::class], 'localized-url.providers');

        $this->app->singleton(BlogSeoMeta::class);
    }

    public function boot(): void
    {
        $this->registerViews();
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'dixlase-blog');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Register the plugin's views, with overrides, in the same order as
     * DixlasePages. The first directory holding a view wins:
     *
     * 1. custom/plugins/DixlaseBlog/resources/views/ (site customisation)
     * 2. themes/<active theme>/plugins/DixlaseBlog/resources/views/
     * 3. plugins/DixlaseBlog/resources/views/ (the defaults)
     */
    public function registerViews(): void
    {
        foreach ($this->overridePaths() as $path) {
            if (is_dir($path)) {
                View::addNamespace('dixlase-blog', $path);
            }
        }

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'dixlase-blog');
    }

    /**
     * Override directories, highest priority first.
     *
     * @return list<string>
     */
    public function overridePaths(): array
    {
        $relative = 'plugins/DixlaseBlog/resources/views';
        $paths = [base_path(trim((string) config('custom.custom_files_dir', 'custom'), '/').'/'.$relative)];

        $themeDirectory = $this->enabledThemeDirectory();
        if ($themeDirectory !== null) {
            $paths[] = base_path(config('themes.theme_directory', 'themes').'/'.$themeDirectory.'/'.$relative);
        }

        return $paths;
    }

    /**
     * Catches Throwable because the theme repository reads from the
     * database; during install / migrate the theme table may not exist yet.
     */
    private function enabledThemeDirectory(): ?string
    {
        try {
            if (! app()->bound(ThemeRepositoryInterface::class)) {
                return null;
            }
            $directory = app(ThemeRepositoryInterface::class)->getEnabledThemeDirectory();

            return is_string($directory) && $directory !== '' ? $directory : null;
        } catch (Throwable) {
            return null;
        }
    }
}
