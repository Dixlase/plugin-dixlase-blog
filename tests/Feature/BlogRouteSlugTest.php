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

use App\Contracts\RouteSlugProvider;
use App\Contracts\TranslationResolver;
use App\DTO\RouteSlug\RegisteredSlug;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Enums\MemberRole;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\Member;
use App\Services\RouteSlugRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Services\BlogPostLinkableProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The blog's URL prefix (route_slug): where the public routes live, and
 * how core's RouteSlugRegistry keeps it apart from other features.
 */
class BlogRouteSlugTest extends TestCase
{
    use RefreshDatabase;

    private string $layoutDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app->singletonIf(RouteSlugRegistry::class);
        $this->app->singleton(BlogSettings::class);
        (new DixlaseBlogServiceProvider($this->app))->boot();
        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());
        app()->setLocale('en');

        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put($this->layoutDirectory.'/layouts/app.blade.php', "<html><body>@yield('content')</body></html>");
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        BlogPost::create([
            'title' => 'Hello',
            'slug' => 'hello',
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'en',
            'published_at' => now()->subDay(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_the_blog_lives_under_blog_by_default(): void
    {
        $this->loadFrontRoutes();

        $this->get('/blog/hello')->assertOk();
        $this->assertSame(url('/blog/hello'), route('dixlase-blog::front.show', ['slug' => 'hello']));
    }

    public function test_the_blog_moves_to_the_configured_prefix(): void
    {
        app(BlogSettings::class)->save(['route_slug' => 'news']);
        $this->loadFrontRoutes();

        $this->get('/news')->assertOk()->assertSee(url('/news/hello'), false);
        $this->get('/news/hello')->assertOk();
        $this->get('/news/feed.xml')->assertOk();
        $this->get('/blog/hello')->assertNotFound();
        $this->assertSame('/news/hello', app(BlogPostLinkableProvider::class)->getItemById((string) BlogPost::query()->value('id'))?->url);
    }

    public function test_core_sees_the_blog_prefix(): void
    {
        app(BlogSettings::class)->save(['route_slug' => 'news']);

        $conflict = app(RouteSlugRegistry::class)->findConflict('news', 'someone-else:slug');

        $this->assertSame(DixlaseBlogServiceProvider::ROUTE_SLUG_OWNER, $conflict?->owner);
        $this->assertNull(app(RouteSlugRegistry::class)->findConflict('news', DixlaseBlogServiceProvider::ROUTE_SLUG_OWNER));
    }

    public function test_a_prefix_another_feature_uses_is_refused(): void
    {
        app(RouteSlugRegistry::class)->registerProvider('other-plugin', new class implements RouteSlugProvider
        {
            public function getRouteSlugs(): array
            {
                return [new RegisteredSlug(slug: 'page', owner: 'other-plugin:route_slug', label: 'Other plugin')];
            }
        });

        $this->saveSlug('page')->assertSessionHasErrors('route_slug');
        $this->assertSame('blog', app(BlogSettings::class)->routeSlug());
    }

    public function test_reserved_words_locale_codes_and_bad_formats_are_refused(): void
    {
        foreach (['api', 'search', 'ja', 'en', 'News', 'my blog', '-blog', str_repeat('a', 51), ''] as $slug) {
            $this->saveSlug($slug)->assertSessionHasErrors('route_slug');
        }

        $this->assertSame('blog', app(BlogSettings::class)->routeSlug());
    }

    public function test_a_free_prefix_is_saved(): void
    {
        $this->saveSlug('column-2026')->assertSessionHasNoErrors();

        app()->forgetInstance(BlogSettings::class);
        $this->assertSame('column-2026', app(BlogSettings::class)->routeSlug());
    }

    public function test_a_bad_stored_value_falls_back_to_blog(): void
    {
        app(BlogSettings::class)->save(['route_slug' => '../admin']);

        $this->assertSame('blog', app(BlogSettings::class)->routeSlug());
    }

    private function saveSlug(string $slug): \Illuminate\Testing\TestResponse
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        return $this->put(route('dixlase-blog::admin.blog.settings.update'), ['route_slug' => $slug]);
    }

    private function loadFrontRoutes(): void
    {
        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
