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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Services\BlogPostNeighbours;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The previous, next and related posts under a post.
 */
class BlogPostNeighboursTest extends TestCase
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
        app()->setLocale('en');

        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put($this->layoutDirectory.'/layouts/app.blade.php', "<html><body>@yield('content')</body></html>");
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        $this->travelTo(Carbon::create(2026, 10, 20, 12));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_previous_and_next_skip_posts_that_are_not_visible(): void
    {
        $this->makePost('First', 1);
        $this->makePost('Hidden draft', 2, ContentStatus::DRAFT);
        $middle = $this->makePost('Middle', 3);
        $this->makePost('Future', 30);
        $this->makePost('Last', 5);

        $neighbours = app(BlogPostNeighbours::class);
        $this->assertSame('First', $neighbours->previous($middle)?->title);
        $this->assertSame('Last', $neighbours->next($middle)?->title);

        $this->get('/blog/middle')->assertOk()
            ->assertSeeInOrder(['Previous post', 'First', 'Next post', 'Last'])
            ->assertDontSee('Hidden draft')
            ->assertDontSee('Future');
    }

    public function test_the_first_and_last_posts_have_one_side_only(): void
    {
        $first = $this->makePost('First', 1);
        $last = $this->makePost('Last', 2);

        $neighbours = app(BlogPostNeighbours::class);
        $this->assertNull($neighbours->previous($first));
        $this->assertSame('Last', $neighbours->next($first)?->title);
        $this->assertNull($neighbours->next($last));
    }

    public function test_posts_published_at_the_same_moment_are_ordered_by_id(): void
    {
        $a = $this->makePost('Same A', 1);
        $b = $this->makePost('Same B', 1);

        $neighbours = app(BlogPostNeighbours::class);
        $this->assertSame($b->id, $neighbours->next($a)?->id);
        $this->assertSame($a->id, $neighbours->previous($b)?->id);
    }

    public function test_a_post_without_a_publish_date_has_no_neighbours(): void
    {
        $this->makePost('Before', 1);
        $draft = $this->makePost('Undated draft', 2, ContentStatus::DRAFT);
        $draft->update(['published_at' => null]);
        $this->makePost('After', 3);

        $neighbours = app(BlogPostNeighbours::class);
        $this->assertNull($neighbours->previous($draft->fresh()));
        $this->assertNull($neighbours->next($draft->fresh()));
    }

    public function test_related_posts_share_a_category_or_tag_most_shared_first(): void
    {
        $news = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 0]);
        $release = BlogTag::create(['name' => 'Release', 'slug' => 'release']);
        $notes = BlogTag::create(['name' => 'Notes', 'slug' => 'notes']);

        $post = $this->makePost('This post', 10, categories: [$news->id], tags: [$release->id, $notes->id]);
        $this->makePost('Shares all three', 1, categories: [$news->id], tags: [$release->id, $notes->id]);
        $this->makePost('Shares one, newer', 9, tags: [$notes->id]);
        $this->makePost('Shares one, older', 2, tags: [$release->id]);
        $this->makePost('Shares nothing', 8);
        $this->makePost('Draft sharing all', 3, ContentStatus::DRAFT, categories: [$news->id], tags: [$release->id]);

        $related = app(BlogPostNeighbours::class)->related($post->fresh(['categories', 'tags']));

        $this->assertSame(['Shares all three', 'Shares one, newer', 'Shares one, older'], $related->pluck('title')->all());
    }

    public function test_a_post_without_categories_or_tags_has_no_related_posts(): void
    {
        $post = $this->makePost('Alone', 1);
        $this->makePost('Other', 2);

        $this->assertTrue(app(BlogPostNeighbours::class)->related($post->fresh(['categories', 'tags']))->isEmpty());
        $this->get('/blog/alone')->assertOk()->assertDontSee('Related posts');
    }

    /**
     * @param  list<int>  $categories
     * @param  list<int>  $tags
     */
    private function makePost(string $title, int $day, ContentStatus $status = ContentStatus::PUBLISHED, array $categories = [], array $tags = []): BlogPost
    {
        $post = BlogPost::create([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => $status,
            'lang' => 'en',
            'published_at' => Carbon::create(2026, 10, 1, 9)->addDays($day - 1),
        ]);
        $post->categories()->attach($categories);
        $post->tags()->attach($tags);

        return $post;
    }
}
