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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The blog settings screen stores its values, and the public pages read
 * them.
 */
class BlogSettingsTest extends TestCase
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
        $this->app->singleton(BlogSettings::class);
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

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_settings_read_as_their_defaults_until_saved(): void
    {
        $this->assertSame(0, DB::table('plg_dixlase_blog_settings')->count());
        $this->assertSame(10, app(BlogSettings::class)->postsPerPage());
    }

    public function test_core_is_told_the_table_and_every_default(): void
    {
        $provider = new DixlaseBlogServiceProvider($this->app);

        $this->assertSame('plg_dixlase_blog_settings', $provider->getSettingsTable());
        $this->assertSame(BlogSettings::DEFAULTS, $provider->getSettingsDefaults());
    }

    public function test_the_settings_are_saved(): void
    {
        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['posts_per_page' => '5'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dixlase-blog::admin.blog.settings.listing'));

        $this->assertSame('5', DB::table('plg_dixlase_blog_settings')->where('name', 'posts_per_page')->value('value'));
        $this->assertSame(5, app(BlogSettings::class)->postsPerPage());
    }

    public function test_each_screen_shows_only_its_own_fields(): void
    {
        $fields = [
            'listing' => 'post_fields[author]',
            'sidebar' => 'sidebar_recent_count',
            'share' => 'share_services[x]',
            'comments' => 'comment_link_limit',
        ];

        foreach ($fields as $screen => $field) {
            $response = $this->get(route('dixlase-blog::admin.blog.settings.'.$screen))
                ->assertOk()
                ->assertSee('name="screen" value="'.$screen.'"', false)
                ->assertSee('name="'.$field.'"', false);

            foreach (array_diff_key($fields, [$screen => true]) as $other) {
                $response->assertDontSee('name="'.$other.'"', false);
            }
        }
    }

    public function test_the_old_address_opens_the_listing_screen(): void
    {
        $this->get(route('dixlase-blog::admin.blog.settings.edit'))
            ->assertRedirect(route('dixlase-blog::admin.blog.settings.listing'));
    }

    public function test_saving_a_screen_returns_to_it_and_leaves_the_others_alone(): void
    {
        app(BlogSettings::class)->save(['posts_per_page' => 7]);

        $this->put(route('dixlase-blog::admin.blog.settings.update'), [
            'screen' => 'comments',
            'comment_link_limit' => '4',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dixlase-blog::admin.blog.settings.comments'));

        app()->forgetInstance(BlogSettings::class);
        $this->assertSame('4', DB::table('plg_dixlase_blog_settings')->where('name', 'comment_link_limit')->value('value'));
        $this->assertSame(7, app(BlogSettings::class)->postsPerPage());
        $this->assertNull(DB::table('plg_dixlase_blog_settings')->where('name', 'screen')->first());
    }

    public function test_an_unknown_screen_is_refused(): void
    {
        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['screen' => 'theme', 'posts_per_page' => '5'])
            ->assertSessionHasErrors('screen');

        $this->assertSame(0, DB::table('plg_dixlase_blog_settings')->count());
    }

    public function test_the_post_fields_are_saved_in_a_fixed_order(): void
    {
        $this->assertSame(BlogSettings::POST_FIELDS, app(BlogSettings::class)->postFields());
        $this->assertTrue(app(BlogSettings::class)->cardExcerpt());
        $this->assertSame('full', app(BlogSettings::class)->latestBody());

        $this->put(route('dixlase-blog::admin.blog.settings.update'), [
            'screen' => 'listing',
            'post_fields' => ['tags' => '1', 'date' => '1', 'author' => '0', 'featured_image' => '0', 'categories' => '0', 'comments' => '0', 'title' => '0'],
            'card_excerpt' => '0',
            'latest_body' => 'excerpt',
        ])->assertSessionHasNoErrors();

        app()->forgetInstance(BlogSettings::class);
        $settings = app(BlogSettings::class);
        $this->assertSame(['date', 'tags'], $settings->postFields());
        $this->assertFalse($settings->cardExcerpt());
        $this->assertSame('excerpt', $settings->latestBody());

        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['latest_body' => 'both'])
            ->assertSessionHasErrors('latest_body');
    }

    public function test_the_cards_show_only_the_chosen_fields(): void
    {
        $this->bootWithoutSidebar();
        $this->postWithEverything();

        $this->get('/blog')->assertOk()
            ->assertSee('Card excerpt')
            ->assertSee('#Cardtag')
            ->assertSee('Card Author')
            ->assertSee('<time', false);

        app(BlogSettings::class)->save(['post_fields' => [], 'card_excerpt' => false]);

        $this->get('/blog')->assertOk()
            ->assertSee('Card title')
            ->assertDontSee('Card excerpt')
            ->assertDontSee('#Cardtag')
            ->assertDontSee('Card Author')
            ->assertDontSee('dixlase-blog-meta', false);
    }

    public function test_the_latest_posts_show_the_full_text_by_default(): void
    {
        $this->bootWithoutSidebar();
        $this->postWithEverything();
        app(BlogSettings::class)->save(['index_layout' => 'full']);

        $this->get('/blog')->assertOk()
            ->assertSee('<p>Card body</p>', false)
            ->assertDontSee('Card excerpt')
            ->assertDontSee('dixlase-blog-post__more', false)
            ->assertSee('#Cardtag')
            ->assertSee('Card Author')
            ->assertSee(route('dixlase-blog::front.custom-style', ['slug' => 'card']), false);
    }

    public function test_the_latest_posts_can_show_the_excerpt_and_a_link_instead(): void
    {
        $this->bootWithoutSidebar();
        $this->postWithEverything();
        app(BlogSettings::class)->save(['index_layout' => 'full', 'latest_body' => 'excerpt', 'post_fields' => ['date']]);

        $this->get('/blog')->assertOk()
            ->assertSee('Card excerpt')
            ->assertDontSee('<p>Card body</p>', false)
            ->assertSee('<a href="'.route('dixlase-blog::front.show', ['slug' => 'card']).'" aria-label="'.e(__('dixlase-blog::front.post.read_more_label', ['title' => 'Card title'])).'">'.e(__('dixlase-blog::front.post.read_more')).'</a>', false)
            // No body, so none of its CSS either.
            ->assertDontSee(route('dixlase-blog::front.custom-style', ['slug' => 'card']), false)
            // The switches apply here too.
            ->assertSee('<time', false)
            ->assertDontSee('#Cardtag')
            ->assertDontSee('Card Author');
    }

    public function test_the_post_page_ignores_the_listing_switches(): void
    {
        $this->bootWithoutSidebar();
        $this->postWithEverything();
        app(BlogSettings::class)->save(['post_fields' => [], 'latest_body' => 'excerpt']);

        $this->get('/blog/card')->assertOk()
            ->assertSee('<p>Card body</p>', false)
            ->assertSee('#Cardtag')
            ->assertSee('Card Author');
    }

    public function test_the_cards_can_be_laid_out_in_one_column(): void
    {
        $this->bootWithoutSidebar();
        $this->postWithEverything();
        $this->assertSame('multi', app(BlogSettings::class)->cardColumns());

        $this->get('/blog')->assertOk()
            ->assertSee('<div class="dixlase-blog-list">', false);

        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['screen' => 'listing', 'card_columns' => 'single'])
            ->assertSessionHasNoErrors();
        app()->forgetInstance(BlogSettings::class);

        // The top page and the archives alike.
        $this->get('/blog')->assertOk()
            ->assertSee('<div class="dixlase-blog-list dixlase-blog-list--single">', false);
        $this->get('/blog/tag/cardtag')->assertOk()
            ->assertSee('<div class="dixlase-blog-list dixlase-blog-list--single">', false);

        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['card_columns' => 'three'])
            ->assertSessionHasErrors('card_columns');
    }

    private function bootWithoutSidebar(): void
    {
        (new DixlaseBlogServiceProvider($this->app))->boot();
        // Only the posts: the sidebar lists tags and recent posts too.
        app(BlogSettings::class)->save(['sidebar_enabled' => false]);
        // A layout with the stacks, where the posts' CSS link lands.
        File::put($this->layoutDirectory.'/layouts/app.blade.php', "<html><head>@stack('styles')</head><body>@yield('content')@stack('scripts')</body></html>");
    }

    private function postWithEverything(): BlogPost
    {
        $author = Member::factory()->create(['display_name' => 'Card Author', 'status' => 1]);
        $post = BlogPost::create([
            'title' => 'Card title',
            'slug' => 'card',
            'content' => 'Card body',
            'excerpt' => 'Card excerpt',
            'custom_css' => 'p{color:red}',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'en',
            'user_id' => $author->id,
            'published_at' => now()->subDay(),
        ]);
        $post->tags()->attach(BlogTag::create(['name' => 'Cardtag', 'slug' => 'cardtag', 'lang' => 'en'])->id);

        return $post;
    }

    public function test_posts_per_page_must_be_in_range(): void
    {
        foreach (['0', '101', 'ten', ''] as $value) {
            $this->put(route('dixlase-blog::admin.blog.settings.update'), ['posts_per_page' => $value])
                ->assertSessionHasErrors('posts_per_page');
        }

        $this->assertSame(0, DB::table('plg_dixlase_blog_settings')->count());
    }

    public function test_unknown_fields_are_not_stored(): void
    {
        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['posts_per_page' => '5', 'something_else' => 'x']);

        $this->assertSame(['posts_per_page'], DB::table('plg_dixlase_blog_settings')->pluck('name')->all());
    }

    public function test_the_blog_index_shows_the_configured_number_of_posts(): void
    {
        foreach (range(1, 3) as $n) {
            BlogPost::create([
                'title' => 'Post number '.$n,
                'slug' => 'post-'.$n,
                'content' => 'Body',
                'editor_type' => ContentEditorType::MARKDOWN,
                'status' => ContentStatus::PUBLISHED,
                'lang' => 'en',
                'published_at' => now()->subDays($n),
            ]);
        }
        app(BlogSettings::class)->save(['posts_per_page' => 2]);

        $this->get('/blog')->assertOk()
            ->assertSee('Post number 1')
            ->assertSee('Post number 2')
            ->assertDontSee('Post number 3');
        $this->get('/blog?page=2')->assertOk()->assertSee('Post number 3');
    }

    public function test_the_top_page_layout_is_saved(): void
    {
        $this->assertSame('cards', app(BlogSettings::class)->indexLayout());

        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['index_layout' => 'full'])
            ->assertSessionHasNoErrors();
        $this->assertSame('full', app(BlogSettings::class)->indexLayout());
    }

    public function test_the_top_page_layout_must_be_known(): void
    {
        $this->put(route('dixlase-blog::admin.blog.settings.update'), ['index_layout' => 'grid'])
            ->assertSessionHasErrors('index_layout');

        $this->assertSame(0, DB::table('plg_dixlase_blog_settings')->count());
    }

    public function test_the_top_page_shows_the_latest_posts_in_full_and_pages(): void
    {
        // A layout with the stacks, where the posts' CSS link lands.
        File::put($this->layoutDirectory.'/layouts/app.blade.php', "<html><head>@stack('styles')</head><body>@yield('content')@stack('scripts')</body></html>");

        foreach (range(1, 3) as $n) {
            BlogPost::create([
                'title' => 'Post number '.$n,
                'slug' => 'post-'.$n,
                'content' => "Full body {$n}\n\nSecond paragraph {$n}",
                'excerpt' => 'Excerpt '.$n,
                'editor_type' => ContentEditorType::MARKDOWN,
                'status' => ContentStatus::PUBLISHED,
                'lang' => 'en',
                'published_at' => now()->subDays($n),
            ]);
        }
        BlogPost::create([
            'title' => 'Styled post',
            'slug' => 'styled',
            'content' => '<p>Styled body</p>',
            'custom_css' => 'p{color:red}',
            'custom_js' => 'alert(1)',
            'editor_type' => ContentEditorType::HTML,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'en',
            'published_at' => now(),
        ]);
        app(BlogSettings::class)->save(['index_layout' => 'full', 'posts_per_page' => 2]);

        $this->get('/blog')->assertOk()
            ->assertSee('Styled body', false)
            ->assertSee('<p>Full body 1</p>', false)
            ->assertSee('Second paragraph 1')
            ->assertDontSee('Post number 2')
            ->assertSee('<h2 class="dixlase-blog-post__title" lang="en"><a href="'.route('dixlase-blog::front.show', ['slug' => 'post-1']).'">Post number 1</a></h2>', false)
            // The HTML post's CSS, never its script.
            ->assertSee(route('dixlase-blog::front.custom-style', ['slug' => 'styled']), false)
            ->assertDontSee('custom-script.js', false)
            ->assertSee('page=2', false);
        $this->get('/blog?page=2')->assertOk()->assertSee('<p>Full body 2</p>', false)->assertSee('Post number 3');

        // Back on cards, the top page shows excerpts again.
        app(BlogSettings::class)->save(['index_layout' => 'cards']);
        $this->get('/blog')->assertOk()->assertSee('Excerpt 1')->assertDontSee('Second paragraph 1');
    }
}
