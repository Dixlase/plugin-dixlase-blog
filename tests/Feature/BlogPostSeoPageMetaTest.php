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
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Plugins\DixlaseBlog\Tests\Support\SeoPageMetaRecorder;
use Tests\TestCase;

/**
 * What the post page hands to DixlaseSEO's dls_seo_set_page_meta(): the
 * post, the plugin slug, the title in the language shown, the featured
 * image as the fallback OGP image, and "article". DixlaseSEO itself is
 * replaced by a recording stand-in.
 */
class BlogPostSeoPageMetaTest extends TestCase
{
    use RefreshDatabase;

    private string $layoutDirectory;

    private FakeTranslationResolver $translations;

    protected function setUp(): void
    {
        parent::setUp();

        if (! SeoPageMetaRecorder::install()) {
            $this->markTestSkipped('DixlaseSEO is loaded; its own helper cannot be observed.');
        }

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));

        // A bare layout in a throwaway directory stands in for the theme.
        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put(
            $this->layoutDirectory.'/layouts/app.blade.php',
            "<html><head>@stack('head')</head><body>@yield('content')</body></html>",
        );
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        Route::prefix('{locale}')
            ->where(['locale' => 'ja|en'])
            ->middleware(['web', SetFrontLocale::class])
            ->name('dixlase-blog::front.')
            ->group(function () {
                Route::get('/blog', [DixlaseBlogFrontController::class, 'index'])->name('index');
                Route::get('/blog/category/{slug}', [DixlaseBlogFrontController::class, 'category'])->name('category');
                Route::get('/blog/tag/{slug}', [DixlaseBlogFrontController::class, 'tag'])->name('tag');
                Route::get('/blog/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('show');
            });
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->translations = new FakeTranslationResolver();
        $this->app->instance(TranslationResolver::class, $this->translations);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_the_post_page_passes_its_title_featured_image_and_type(): void
    {
        // The id is only handed on; DixlaseSEO resolves it to a file.
        $post = $this->makePost(['featured_media_id' => 42]);

        $this->get('/ja/blog/hello')->assertOk();

        $this->assertCount(1, SeoPageMetaRecorder::$calls);
        [$page, $slug, $title, $fallbackOgpMediaId, $ogType] = SeoPageMetaRecorder::$calls[0];
        $this->assertTrue($post->is($page));
        $this->assertSame('dixlase-blog', $slug);
        $this->assertSame('日本語のタイトル', $title);
        $this->assertSame(42, $fallbackOgpMediaId);
        $this->assertSame('article', $ogType);
    }

    public function test_the_title_follows_the_language_shown(): void
    {
        $post = $this->makePost();
        $this->translations->set($post, 'en', ['title' => 'English title']);

        $this->get('/en/blog/hello')->assertOk();
        $this->get('/ja/blog/hello')->assertOk();

        $this->assertSame('English title', SeoPageMetaRecorder::$calls[0][2]);
        $this->assertSame('日本語のタイトル', SeoPageMetaRecorder::$calls[1][2]);
    }

    public function test_a_post_without_a_featured_image_passes_none(): void
    {
        $this->makePost();

        $this->get('/ja/blog/hello')->assertOk();

        $this->assertNull(SeoPageMetaRecorder::$calls[0][3]);
    }

    public function test_the_arguments_are_positional(): void
    {
        $this->makePost();

        $this->get('/ja/blog/hello')->assertOk();

        // DixlaseSEO 0.1.x declares two parameters. PHP drops extra
        // positional arguments but throws on an unknown named one, so a
        // named argument here would break the post page on those sites.
        $this->assertSame([0, 1, 2, 3, 4], array_keys(SeoPageMetaRecorder::$calls[0]));
    }

    public function test_dixlase_seo_writes_the_head_instead_of_the_post(): void
    {
        $this->makePost();

        $this->get('/ja/blog/hello')
            ->assertOk()
            ->assertDontSee('<meta property="og:title"', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePost(array $attributes = []): BlogPost
    {
        return BlogPost::create($attributes + [
            'title' => '日本語のタイトル',
            'slug' => 'hello',
            'content' => '日本語の本文',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'ja',
            'published_at' => now()->subDay(),
        ]);
    }
}
