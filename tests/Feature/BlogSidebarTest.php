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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The blog sidebar: shown, placed and filled as the settings say, on the
 * index, the archives and the post pages.
 */
class BlogSidebarTest extends TestCase
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

        // The provider registers the views, the translations and the
        // sidebar's view composer, as on a site.
        $this->app->singleton(BlogSettings::class);
        (new DixlaseBlogServiceProvider($this->app))->boot();
        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());
        app()->setLocale('en');

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

        $this->travelTo(Carbon::create(2026, 10, 20, 12));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_every_blog_page_shows_the_sidebar_by_default(): void
    {
        $this->makePost('Hello', Carbon::create(2026, 10, 10));

        foreach (['/blog', '/blog/hello', '/blog/2026/10', '/blog/search?q=hello'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('dixlase-blog--with-sidebar', false)
                ->assertSee('dixlase-blog-widget--search', false);
        }
    }

    public function test_the_sidebar_can_be_turned_off(): void
    {
        $this->makePost('Hello', Carbon::create(2026, 10, 10));
        app(BlogSettings::class)->save(['sidebar_enabled' => false]);

        $this->get('/blog')->assertOk()->assertDontSee('dixlase-blog-sidebar', false);
    }

    public function test_the_sidebar_goes_left_and_keeps_the_widget_order(): void
    {
        $this->makePost('Hello', Carbon::create(2026, 10, 10), tags: ['Release']);
        app(BlogSettings::class)->save([
            'sidebar_position' => 'left',
            'sidebar_widgets' => [
                ['key' => 'tags', 'enabled' => true],
                ['key' => 'recent', 'enabled' => true],
                ['key' => 'search', 'enabled' => false],
            ],
        ]);

        $response = $this->get('/blog')->assertOk()
            ->assertSee('dixlase-blog--sidebar-left', false)
            ->assertDontSee('dixlase-blog-widget--search', false)
            ->assertSeeInOrder(['dixlase-blog-widget--tags', 'dixlase-blog-widget--recent'], false);

        // Widgets missing from the stored list are appended, enabled.
        $response->assertSee('dixlase-blog-widget--calendar', false);
    }

    public function test_widgets_list_only_visible_posts(): void
    {
        $news = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'depth' => 0, 'display_order' => 0]);
        $empty = BlogCategory::create(['label' => 'Empty category', 'slug' => 'empty', 'depth' => 0, 'display_order' => 1]);
        $this->makePost('Visible post', Carbon::create(2026, 9, 3), categories: [$news->id], tags: ['Shown tag']);
        $this->makePost('Draft post', Carbon::create(2026, 8, 3), ContentStatus::DRAFT, categories: [$empty->id], tags: ['Hidden tag']);

        $this->get('/blog')->assertOk()
            ->assertSee('Visible post')
            ->assertSee('September 2026')
            ->assertDontSee('August 2026')
            ->assertSee('Shown tag')
            ->assertDontSee('Hidden tag')
            ->assertSee('News')
            ->assertDontSee('Empty category');
    }

    public function test_child_categories_are_nested_under_their_parent(): void
    {
        $parent = BlogCategory::create(['label' => 'Parent category', 'slug' => 'parent', 'display_order' => 0]);
        $child = BlogCategory::create(['label' => 'Child category', 'slug' => 'child', 'parent_id' => $parent->id, 'display_order' => 0]);
        // Only the child has a post; the parent still shows, to hold it.
        $this->makePost('Hello', Carbon::create(2026, 10, 10), categories: [$child->id]);

        $this->get('/blog')->assertOk()
            ->assertSeeInOrder(['Parent category', '(0)', 'Child category', '(1)']);
    }

    public function test_recent_posts_follow_the_configured_count(): void
    {
        foreach (range(1, 4) as $n) {
            $this->makePost('Recent '.$n, Carbon::create(2026, 10, $n));
        }
        app(BlogSettings::class)->save([
            'sidebar_recent_count' => 2,
            'sidebar_widgets' => [
                ['key' => 'recent', 'enabled' => true],
                ['key' => 'search', 'enabled' => false],
                ['key' => 'categories', 'enabled' => false],
                ['key' => 'archives', 'enabled' => false],
                ['key' => 'calendar', 'enabled' => false],
                ['key' => 'tags', 'enabled' => false],
            ],
        ]);

        // An empty month lists no posts, so only the sidebar shows titles.
        $this->get('/blog/2025/01')->assertOk()
            ->assertSee('Recent 4')
            ->assertSee('Recent 3')
            ->assertDontSee('Recent 2');
    }

    public function test_the_calendar_links_days_with_posts_and_neighbouring_months(): void
    {
        $this->makePost('August post', Carbon::create(2026, 8, 15));
        $this->makePost('October post', Carbon::create(2026, 10, 7));

        $this->get('/blog/2026/10')->assertOk()
            ->assertSee('<caption>October 2026</caption>', false)
            ->assertSee('href="'.url('/blog/2026/10/07').'"', false)
            ->assertSee('href="'.url('/blog/2026/08').'"', false);

        // A month archive moves the calendar to that month.
        $this->get('/blog/2026/08')->assertOk()
            ->assertSee('<caption>August 2026</caption>', false)
            ->assertSee('href="'.url('/blog/2026/08/15').'"', false)
            ->assertSee('href="'.url('/blog/2026/10').'"', false);
    }

    public function test_the_settings_screen_saves_the_sidebar(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        $this->put(route('dixlase-blog::admin.blog.settings.update'), [
            'posts_per_page' => '12',
            'sidebar_enabled' => '1',
            'sidebar_position' => 'left',
            'sidebar_widgets' => ['tags', 'search', 'categories', 'recent', 'archives', 'calendar'],
            'sidebar_widget_enabled' => ['tags' => '1', 'search' => '0', 'categories' => '1', 'recent' => '1', 'archives' => '1', 'calendar' => '0'],
            'sidebar_recent_count' => '3',
        ])->assertSessionHasNoErrors();

        app()->forgetInstance(BlogSettings::class);
        $settings = app(BlogSettings::class);
        $this->assertSame('left', $settings->sidebarPosition());
        $this->assertSame(3, $settings->sidebarRecentCount());
        $this->assertSame(
            [['key' => 'tags', 'enabled' => true], ['key' => 'search', 'enabled' => false]],
            array_slice($settings->sidebarWidgets(), 0, 2),
        );
    }

    public function test_the_settings_screen_refuses_unknown_or_repeated_widgets(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        $base = ['posts_per_page' => '12', 'sidebar_enabled' => '1', 'sidebar_position' => 'right', 'sidebar_recent_count' => '5'];

        $this->put(route('dixlase-blog::admin.blog.settings.update'), $base + ['sidebar_widgets' => ['search', 'weather']])
            ->assertSessionHasErrors('sidebar_widgets.1');
        $this->put(route('dixlase-blog::admin.blog.settings.update'), $base + ['sidebar_widgets' => ['search', 'search']])
            ->assertSessionHasErrors('sidebar_widgets.0');
        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['sidebar_position' => 'top'] + $base + ['sidebar_widgets' => ['search']])
            ->assertSessionHasErrors('sidebar_position');
    }

    public function test_every_widget_has_a_label_and_a_template(): void
    {
        foreach (BlogSettings::SIDEBAR_WIDGETS as $key) {
            $this->assertNotSame(
                'dixlase-blog::admin.settings.sidebar_widget_'.$key,
                __('dixlase-blog::admin.settings.sidebar_widget_'.$key),
                $key,
            );
            $this->assertTrue(view()->exists('dixlase-blog::front.sidebar.'.$key), $key);
        }
    }

    /**
     * @param  list<int>  $categories
     * @param  list<string>  $tags
     */
    private function makePost(string $title, Carbon $publishedAt, ContentStatus $status = ContentStatus::PUBLISHED, array $categories = [], array $tags = []): BlogPost
    {
        $post = BlogPost::create([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => $status,
            'lang' => 'en',
            'published_at' => $publishedAt,
        ]);
        $post->categories()->attach($categories);
        foreach ($tags as $name) {
            $post->tags()->attach(BlogTag::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name, 'lang' => 'en'])->id);
        }

        return $post;
    }
}
