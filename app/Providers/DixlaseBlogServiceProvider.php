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

use App\Contracts\Extension\ProvidesSettingsDefaultsInterface;
use App\Contracts\Repositories\ThemeRepositoryInterface;
use App\Contracts\RouteSlugProvider;
use App\DTO\RouteSlug\RegisteredSlug;
use App\Helpers\PluginHelper;
use App\Services\RouteSlugRegistry;
use App\Traits\PluginLoaderTrait;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\DixlaseBlog\App\Services\BlogCommentCaptchaFormProvider;
use Plugins\DixlaseBlog\App\Services\BlogCommentDashboardProvider;
use Plugins\DixlaseBlog\App\Services\BlogLocalizedUrlProvider;
use Plugins\DixlaseBlog\App\Services\BlogPostLinkableProvider;
use Plugins\DixlaseBlog\App\Services\BlogSeoMeta;
use Plugins\DixlaseBlog\App\Services\BlogSidebar;
use Plugins\DixlaseBlog\App\Shortcodes\BlogShortcode;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Throwable;

class DixlaseBlogServiceProvider extends ServiceProvider implements ProvidesSettingsDefaultsInterface, RouteSlugProvider
{
    use PluginLoaderTrait;

    /** The owner of the blog's prefix in core's RouteSlugRegistry. */
    public const ROUTE_SLUG_OWNER = 'dixlase-blog:route_slug';

    /** The throttle on the comment form (routes/web.php). */
    public const COMMENT_RATE_LIMITER = 'dixlase-blog-comment';

    public function register(): void
    {
        $this->mergeAdminNavConfig(__DIR__.'/../../config/admin.php');

        // Picked up by DixlaseSEO's sitemap and by link pickers.
        $this->app->singleton(BlogPostLinkableProvider::class);
        $this->app->tag([BlogPostLinkableProvider::class], 'linkable.providers');

        // Read through the localized-url.providers tag for hreflang alternates.
        $this->app->singleton(BlogLocalizedUrlProvider::class);
        $this->app->tag([BlogLocalizedUrlProvider::class], 'localized-url.providers');

        $this->app->singleton(BlogSeoMeta::class);
        $this->app->singleton(BlogSettings::class);

        // Lists the comment form in core's CAPTCHA settings.
        $this->app->singleton(BlogCommentCaptchaFormProvider::class);
        $this->app->tag([BlogCommentCaptchaFormProvider::class], 'plugin.capabilities');

        // The count of comments waiting for approval, on the dashboard.
        $this->app->singleton(BlogCommentDashboardProvider::class);
        $this->app->tag([BlogCommentDashboardProvider::class], 'plugin.capabilities');
    }

    public function boot(): void
    {
        $this->registerViews();
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'dixlase-blog');

        // Core refuses a prefix another plugin, the admin URL or a reserved
        // word already uses (UniqueRouteSlug), and other plugins' checks
        // see the blog's prefix through this registration.
        if ($this->app->bound(RouteSlugRegistry::class)) {
            $this->app->make(RouteSlugRegistry::class)->registerProvider('dixlase-blog', $this);
        }

        self::defineCommentRateLimiter();

        // [blog]: the latest posts in other content, such as the front page.
        PluginHelper::registerShortcode('blog', BlogShortcode::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Commenters' IP addresses are kept only for the set period.
            $schedule->command('dls:blog:prune-comment-ips')->daily();
            // Scheduled posts that are due read as published in the admin.
            // Only the stored status: the public pages show a due post
            // whether or not this has run.
            $schedule->command('dls:blog:publish-scheduled')->everyMinute()->withoutOverlapping();
        });

        // The sidebar is built where the layout is rendered, so a theme or a
        // site that overrides the page templates keeps it.
        View::composer('dixlase-blog::front._layout', function ($view): void {
            $view->with('sidebar', app(BlogSidebar::class)->build(request()));
        });

        // What a post card shows beside its title, wherever the card is
        // used (listings, archives, the [blog] shortcode).
        View::composer('dixlase-blog::front._card', function ($view): void {
            $settings = app(BlogSettings::class);
            $view->with('postFields', $settings->postFields());
            $view->with('cardExcerpt', $settings->cardExcerpt());
        });

        // Migrations are deliberately NOT registered with Laravel's migrator.
        // Core's plugin migrator applies them (dls:plugin:migrate, install,
        // enable) and records them in the plugin migration ledger; a bare
        // `php artisan migrate` would otherwise try to re-create the tables.
    }

    /**
     * Comment posts per visitor address, for the throttle on the comment
     * route. The numbers come from the blog settings screen.
     */
    public static function defineCommentRateLimiter(): void
    {
        RateLimiter::for(self::COMMENT_RATE_LIMITER, function (Request $request) {
            $settings = app(BlogSettings::class);

            return Limit::perMinutes($settings->commentRateDecayMinutes(), $settings->commentRateMaxAttempts())
                ->by((string) $request->ip());
        });
    }

    /**
     * @return array<RegisteredSlug>
     */
    public function getRouteSlugs(): array
    {
        try {
            $slug = $this->app->make(BlogSettings::class)->routeSlug();
        } catch (\Throwable) {
            $slug = BlogSettings::ROUTE_SLUG_DEFAULT;
        }

        return [
            new RegisteredSlug(
                slug: $slug,
                owner: self::ROUTE_SLUG_OWNER,
                label: 'dixlase-blog::route-slug.owners.route_slug',
            ),
        ];
    }

    /**
     * Core inserts the settings rows a site does not have yet on install
     * and update, leaving stored values alone.
     */
    public function getSettingsTable(): string
    {
        return 'plg_dixlase_blog_settings';
    }

    /**
     * @return array<string, string>
     */
    public function getSettingsDefaults(): array
    {
        return BlogSettings::DEFAULTS;
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
