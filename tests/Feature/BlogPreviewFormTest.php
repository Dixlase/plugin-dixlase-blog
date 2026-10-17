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
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFeedController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The sidebar's "Preview" button posts the editor form; the post is shown
 * as the form describes it, saved or not, and nothing is written.
 */
class BlogPreviewFormTest extends TestCase
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
        // Under the admin URL, as in routes/admin.php.
        Route::middleware('web')->prefix('admin-custom')->post('/blog/preview-page/{post?}', [DixlaseBlogFrontController::class, 'previewForm'])
            ->whereNumber('post')
            ->name('dixlase-blog::admin.blog.previewForm');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_a_new_post_is_previewed_without_being_saved(): void
    {
        $this->actingAs($this->member(MemberRole::EDITOR), 'member');
        $category = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'depth' => 0, 'display_order' => 0]);
        BlogTag::create(['name' => 'Release', 'slug' => 'release']);

        $this->post('/admin-custom/blog/preview-page', [
            'title' => 'Unsaved title',
            'content' => 'Some **bold** text',
            'editor_type' => 'markdown',
            'status' => 'draft',
            'lang' => 'en',
            'categories' => [$category->id],
            'tags' => ['Release', 'Brand new tag'],
        ])
            ->assertOk()
            ->assertSee('Unsaved title')
            ->assertSee('<strong>bold</strong>', false)
            ->assertSee('/blog/category/news', false)
            ->assertSee('Brand new tag')
            ->assertSee('dixlase-blog-preview-banner', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertSame(0, BlogPost::query()->count());
        $this->assertSame(1, BlogTag::query()->count());
        $this->assertDatabaseCount('plg_dixlase_blog_post_category', 0);
    }

    public function test_an_edited_post_shows_the_form_and_keeps_what_is_stored(): void
    {
        $this->actingAs($this->member(MemberRole::EDITOR), 'member');
        $post = BlogPost::create([
            'title' => 'Stored title',
            'slug' => 'stored',
            'content' => 'Stored body',
            'editor_type' => ContentEditorType::MARKDOWN,
            // A draft, so the page has no comment form (its route is not
            // registered here).
            'status' => ContentStatus::DRAFT,
            'lang' => 'en',
        ]);

        $this->post('/admin-custom/blog/preview-page/'.$post->id, [
            'title' => 'Edited title',
            'slug' => 'stored',
            'content' => 'Edited body',
            'editor_type' => 'markdown',
            'status' => 'draft',
        ])
            ->assertOk()
            ->assertSee('Edited title')
            ->assertSee('Edited body')
            ->assertDontSee('Stored body');

        $post->refresh();
        $this->assertSame('Stored title', $post->title);
        $this->assertSame('Stored body', $post->content);
    }

    public function test_html_is_shown_as_text_unless_the_member_may_author_scripts(): void
    {
        $form = ['title' => 'Html post', 'content' => '<b>raw markup</b>', 'editor_type' => 'html', 'status' => 'draft'];

        $this->actingAs($this->member(MemberRole::EDITOR), 'member');
        $this->post('/admin-custom/blog/preview-page', $form)
            ->assertOk()
            ->assertDontSee('<b>raw markup</b>', false)
            ->assertSee('&lt;b&gt;raw markup&lt;/b&gt;', false);

        $this->actingAs($this->member(MemberRole::ADMIN), 'member');
        $this->post('/admin-custom/blog/preview-page', $form)
            ->assertOk()
            ->assertSee('<b>raw markup</b>', false);
    }

    public function test_the_form_preview_is_hidden_from_visitors_and_contributors(): void
    {
        $form = ['title' => 'Draft', 'content' => 'Body', 'editor_type' => 'markdown'];

        $this->post('/admin-custom/blog/preview-page', $form)->assertNotFound();

        $this->actingAs($this->member(MemberRole::CONTRIBUTOR), 'member');
        $this->post('/admin-custom/blog/preview-page', $form)->assertNotFound();
    }

    private function member(MemberRole $role): Member
    {
        return Member::factory()->create(['role' => $role, 'status' => 1]);
    }
}
