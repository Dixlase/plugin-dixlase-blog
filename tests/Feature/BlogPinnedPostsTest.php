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
use App\Enums\MemberRole;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * A pinned post comes first on the blog index only; the archives and the
 * feed keep date order.
 */
class BlogPinnedPostsTest extends TestCase
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

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));
        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());

        // A bare layout in a throwaway directory stands in for the theme.
        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put($this->layoutDirectory.'/layouts/app.blade.php', "<html><body>@yield('content')</body></html>");
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_the_index_lists_pinned_posts_first(): void
    {
        $this->makePosts();

        $this->get('/blog')->assertOk()
            ->assertSeeInOrder(['Old pinned post', 'Newest post', 'Middle post'])
            ->assertSee(__('dixlase-blog::front.post.pinned'));
    }

    public function test_archives_and_the_feed_keep_date_order(): void
    {
        $this->makePosts();

        $this->get('/blog/category/news')->assertOk()
            ->assertSeeInOrder(['Newest post', 'Middle post', 'Old pinned post'])
            ->assertDontSee('dixlase-blog-card__pinned');
        $this->get('/blog/feed.xml')->assertOk()
            ->assertSeeInOrder(['Newest post', 'Middle post', 'Old pinned post']);
    }

    public function test_a_post_is_pinned_and_unpinned_from_the_edit_form(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::EDITOR, 'status' => 1]));
        $form = ['title' => 'Hello', 'content' => 'Body', 'editor_type' => 'markdown', 'status' => 'draft'];

        // The toggle sends a hidden "0" before the checkbox's "1".
        $this->post(route('dixlase-blog::admin.blog.store'), $form + ['is_pinned' => '1'])->assertSessionHasNoErrors();
        $post = BlogPost::query()->sole();
        $this->assertTrue($post->is_pinned);

        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $form + ['slug' => 'hello', 'is_pinned' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($post->fresh()->is_pinned);
    }

    public function test_a_post_without_the_field_is_not_pinned(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::EDITOR, 'status' => 1]));

        $this->post(route('dixlase-blog::admin.blog.store'), ['title' => 'Hello', 'content' => 'Body', 'editor_type' => 'markdown', 'status' => 'draft'])
            ->assertSessionHasNoErrors();

        $this->assertFalse(BlogPost::query()->sole()->is_pinned);
    }

    private function makePosts(): void
    {
        $category = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'depth' => 0, 'display_order' => 0]);

        foreach ([
            ['Newest post', 'newest', 1, false],
            ['Middle post', 'middle', 2, false],
            ['Old pinned post', 'old-pinned', 3, true],
        ] as [$title, $slug, $daysAgo, $pinned]) {
            $post = BlogPost::create([
                'title' => $title,
                'slug' => $slug,
                'content' => 'Body',
                'editor_type' => ContentEditorType::MARKDOWN,
                'status' => ContentStatus::PUBLISHED,
                'is_pinned' => $pinned,
                'lang' => 'en',
                'published_at' => now()->subDays($daysAgo),
            ]);
            $post->categories()->attach($category->id);
        }
    }
}
