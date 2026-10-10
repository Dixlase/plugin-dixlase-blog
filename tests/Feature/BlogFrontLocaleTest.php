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

use App\Contracts\TranslationResolver;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\SetFrontLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFeedController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The front under a /{locale} prefix, the way DixlaseMultilingual mounts
 * plugin routes: the same route file is loaded a second time inside a
 * {locale} group with the same route names, and SetFrontLocale fills the
 * locale from the URL.
 */
class BlogFrontLocaleTest extends TestCase
{
    use RefreshDatabase;

    private string $layoutDirectory;

    private BlogPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));

        // A bare layout in a throwaway directory stands in for the theme, so
        // these tests exercise the blog's views without depending on one.
        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put(
            $this->layoutDirectory.'/layouts/app.blade.php',
            "<html lang=\"{{ app()->getLocale() }}\"><title>@yield('title')</title><body>@yield('content')</body></html>",
        );
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        $routes = function () {
            Route::name('dixlase-blog::front.')->group(function () {
                Route::get('/blog', [DixlaseBlogFrontController::class, 'index'])->name('index');
                Route::get('/blog/feed.xml', [DixlaseBlogFeedController::class, 'feed'])->name('feed');
                Route::get('/blog/category/{slug}', [DixlaseBlogFrontController::class, 'category'])->name('category');
                Route::get('/blog/tag/{slug}', [DixlaseBlogFrontController::class, 'tag'])->name('tag');
                Route::get('/blog/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('show');
            });
        };
        // With DixlaseMultilingual on, locale-less requests also get a locale
        // (and URL defaults) before the controller runs.
        Route::middleware(['web', SetFrontLocale::class])->group($routes);
        Route::prefix('{locale}')
            ->where(['locale' => 'ja|en'])
            ->middleware(['web', SetFrontLocale::class])
            ->group($routes);
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $translations = new FakeTranslationResolver();
        $this->app->instance(TranslationResolver::class, $translations);

        $this->post = BlogPost::create([
            'title' => '日本語のタイトル',
            'slug' => 'hello',
            'content' => '日本語の本文',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'ja',
            'published_at' => now()->subDay(),
        ]);
        $translations->set($this->post, 'en', ['title' => 'English title', 'content' => 'English body']);

        $category = BlogCategory::create(['label' => 'お知らせ', 'slug' => 'news', 'lang' => 'ja', 'depth' => 0, 'display_order' => 0]);
        $translations->set($category, 'en', ['label' => 'News']);
        $tag = BlogTag::create(['name' => 'リリース', 'slug' => 'release', 'lang' => 'ja']);
        $this->post->categories()->attach($category->id);
        $this->post->tags()->attach($tag->id);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_post_page_opens_under_each_locale_prefix(): void
    {
        $this->get('/en/blog/hello')
            ->assertOk()
            ->assertSee('English title')
            ->assertSee('English body');

        $this->get('/ja/blog/hello')
            ->assertOk()
            ->assertSee('日本語のタイトル')
            ->assertSee('日本語の本文');
    }

    public function test_post_page_still_opens_without_a_locale_prefix(): void
    {
        $this->get('/blog/hello')->assertOk()->assertSee('日本語のタイトル');
    }

    public function test_unknown_slug_under_a_locale_prefix_is_not_found(): void
    {
        $this->get('/en/blog/missing')->assertNotFound();
    }

    public function test_category_and_tag_archives_open_under_a_locale_prefix(): void
    {
        $this->get('/en/blog/category/news')
            ->assertOk()
            ->assertSee('News')
            ->assertSee('English title');

        $this->get('/en/blog/tag/release')
            ->assertOk()
            ->assertSee('English title');
    }

    public function test_links_stay_in_the_current_locale(): void
    {
        $response = $this->get('/en/blog')->assertOk();

        $response->assertSee(url('/en/blog/hello'), false);
        $response->assertSee(url('/en/blog/category/news'), false);
        $response->assertSee(url('/en/blog/tag/release'), false);
        $response->assertSee(url('/en/blog/feed.xml'), false);
        $response->assertDontSee(url('/blog/hello').'"', false);
    }

    public function test_each_locale_has_its_own_feed(): void
    {
        $this->get('/en/blog/feed.xml')
            ->assertOk()
            ->assertSee('<language>en</language>', false)
            ->assertSee('English title', false)
            ->assertSee(url('/en/blog/hello'), false);

        $this->get('/ja/blog/feed.xml')
            ->assertOk()
            ->assertSee('<language>ja</language>', false)
            ->assertSee('日本語のタイトル', false)
            ->assertSee(url('/ja/blog/hello'), false)
            ->assertDontSee('English title', false);
    }
}
