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
 * Which posts the public side shows. Every post below shares one category
 * and one tag, so each listing (index, category, tag, feed) and the post
 * page itself are checked against the same set.
 */
class BlogPostVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** Titles the public side must show. */
    private const VISIBLE = [
        'live' => 'Live post',
        'closing-later' => 'Closing later post',
    ];

    /** Titles the public side must hide. */
    private const HIDDEN = [
        'draft' => 'Draft post',
        'future' => 'Future post',
        'closed' => 'Closed post',
        'unstamped' => 'Unstamped post',
        'trashed' => 'Trashed post',
    ];

    private string $layoutDirectory;

    protected function setUp(): void
    {
        parent::setUp();

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
            "<html><title>@yield('title')</title><body>@yield('content')</body></html>",
        );
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        Route::middleware('web')->name('dixlase-blog::front.')->group(function () {
            Route::get('/blog', [DixlaseBlogFrontController::class, 'index'])->name('index');
            Route::get('/blog/feed.xml', [DixlaseBlogFeedController::class, 'feed'])->name('feed');
            Route::get('/blog/category/{slug}', [DixlaseBlogFrontController::class, 'category'])->name('category');
            Route::get('/blog/tag/{slug}', [DixlaseBlogFrontController::class, 'tag'])->name('tag');
            Route::get('/blog/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('show');
        });
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());

        $category = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'depth' => 0, 'display_order' => 0]);
        $tag = BlogTag::create(['name' => 'Release', 'slug' => 'release']);

        $posts = [
            'live' => ['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()],
            'closing-later' => ['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay(), 'closed_at' => now()->addDay()],
            'draft' => ['status' => ContentStatus::DRAFT, 'published_at' => now()->subDay()],
            'future' => ['status' => ContentStatus::PUBLISHED, 'published_at' => now()->addDay()],
            'closed' => ['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDays(2), 'closed_at' => now()->subHour()],
            'unstamped' => ['status' => ContentStatus::PUBLISHED, 'published_at' => null],
            'trashed' => ['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()],
        ];
        $titles = self::VISIBLE + self::HIDDEN;

        foreach ($posts as $slug => $attributes) {
            $post = BlogPost::create($attributes + [
                'title' => $titles[$slug],
                'slug' => $slug,
                'content' => 'Body of '.$slug,
                'editor_type' => ContentEditorType::MARKDOWN,
                'lang' => 'en',
            ]);
            $post->categories()->attach($category->id);
            $post->tags()->attach($tag->id);
        }

        BlogPost::query()->where('slug', 'trashed')->firstOrFail()->delete();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_post_page_opens_only_for_visible_posts(): void
    {
        foreach (array_keys(self::VISIBLE) as $slug) {
            $this->get('/blog/'.$slug)->assertOk()->assertSee(self::VISIBLE[$slug]);
        }

        foreach (array_keys(self::HIDDEN) as $slug) {
            $this->get('/blog/'.$slug)->assertNotFound();
        }
    }

    public function test_index_lists_only_visible_posts(): void
    {
        $this->assertListsOnlyVisible($this->get('/blog')->assertOk());
    }

    public function test_category_archive_lists_only_visible_posts(): void
    {
        $this->assertListsOnlyVisible($this->get('/blog/category/news')->assertOk());
    }

    public function test_tag_archive_lists_only_visible_posts(): void
    {
        $this->assertListsOnlyVisible($this->get('/blog/tag/release')->assertOk());
    }

    public function test_feed_lists_only_visible_posts(): void
    {
        $response = $this->get('/blog/feed.xml')->assertOk();

        $this->assertStringStartsWith('application/rss+xml', (string) $response->headers->get('Content-Type'));
        $this->assertListsOnlyVisible($response);
    }

    public function test_a_post_disappears_once_its_closing_time_passes(): void
    {
        $this->get('/blog/closing-later')->assertOk();

        $this->travel(2)->days();

        $this->get('/blog/closing-later')->assertNotFound();
        $this->get('/blog')->assertOk()->assertDontSee(self::VISIBLE['closing-later']);
    }

    public function test_a_future_post_appears_once_its_publishing_time_passes(): void
    {
        $this->get('/blog/future')->assertNotFound();

        $this->travel(2)->days();

        $this->get('/blog/future')->assertOk()->assertSee(self::HIDDEN['future']);
    }

    public function test_unknown_category_and_tag_are_not_found(): void
    {
        $this->get('/blog/category/missing')->assertNotFound();
        $this->get('/blog/tag/missing')->assertNotFound();
    }

    private function assertListsOnlyVisible(\Illuminate\Testing\TestResponse $response): void
    {
        foreach (self::VISIBLE as $title) {
            $response->assertSee($title);
        }
        foreach (self::HIDDEN as $title) {
            $response->assertDontSee($title);
        }
    }
}
