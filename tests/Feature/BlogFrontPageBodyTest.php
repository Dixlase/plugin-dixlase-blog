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

namespace Plugins\DixlaseBlog\Tests\Feature;

use App\Contracts\Front\FrontPageBodyProviderInterface;
use App\Contracts\TranslationResolver;
use App\DTO\Front\FrontPageBodyContext;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Services\Plugin\PluginServiceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Services\BlogFrontAssetUrls;
use Plugins\DixlaseBlog\App\Services\BlogFrontPageBodyProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The blog top offered as the body of a theme's front page (#257).
 *
 * The provider is exercised directly, with no theme and no front page route:
 * what matters is that it returns a body fit to be placed inside someone
 * else's layout, and that the blog's own settings decide how it looks.
 */
class BlogFrontPageBodyTest extends TestCase
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

        // The provider registers the views, the translations and the view
        // composers, as on a site.
        $this->app->singleton(BlogSettings::class);
        // register() carries the capability tags, boot() the views, the
        // translations and the view composers. Both are needed: a provider
        // that is only booted renders but is never found.
        $provider = new DixlaseBlogServiceProvider($this->app);
        $provider->register();
        $provider->boot();
        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());
        app()->setLocale('en');

        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put($this->layoutDirectory.'/layouts/app.blade.php', "<html><body>@yield('content')</body></html>");
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->travelTo(Carbon::create(2026, 10, 20, 12));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    private function provider(): BlogFrontPageBodyProvider
    {
        return app(BlogFrontPageBodyProvider::class);
    }

    private function publishPost(string $title, ?Carbon $publishedAt = null): BlogPost
    {
        return BlogPost::create([
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => 'Body of '.$title,
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'en',
            'published_at' => $publishedAt ?? Carbon::create(2026, 10, 1, 12),
        ]);
    }

    private function context(array $overrides = []): FrontPageBodyContext
    {
        return new FrontPageBodyContext(...array_merge([
            'surface' => FrontPageBodyProviderInterface::SURFACE_FRONT,
            'locale' => 'en',
            'page' => 1,
            'showHeading' => true,
        ], $overrides));
    }

    // =========================================================================
    // The choice on offer
    // =========================================================================

    public function test_one_choice_is_offered(): void
    {
        $this->publishPost('First');

        $bodies = $this->provider()->availableFrontPageBodies();

        $this->assertCount(1, $bodies);
        $this->assertSame(BlogFrontPageBodyProvider::BODY_TOP, $bodies[0]->id);
    }

    /**
     * The contract allows withdrawing a choice when there is nothing to
     * offer, and this deliberately does not: the top page exists before the
     * first post, and its own page says so rather than 404ing. Taking the
     * setting away from an operator setting a site up would be the surprise.
     */
    public function test_the_choice_is_offered_before_any_post_exists(): void
    {
        $this->assertCount(1, $this->provider()->availableFrontPageBodies());
    }

    public function test_an_unknown_body_id_is_declined(): void
    {
        $this->assertNull($this->provider()->renderFrontPageBody('not-a-body', $this->context()));
    }

    // =========================================================================
    // What the body is
    // =========================================================================

    public function test_the_body_carries_the_posts(): void
    {
        $this->publishPost('Hello from the blog');

        $result = $this->provider()->renderFrontPageBody(BlogFrontPageBodyProvider::BODY_TOP, $this->context());

        $this->assertNotNull($result);
        $this->assertStringContainsString('Hello from the blog', $result->html());
    }

    /**
     * A body is placed inside the theme's layout, so it must not bring one.
     */
    public function test_the_body_is_not_a_whole_page(): void
    {
        $this->publishPost('First');

        $html = $this->provider()->renderFrontPageBody(BlogFrontPageBodyProvider::BODY_TOP, $this->context())->html();

        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('<body', $html);
    }

    public function test_the_heading_is_suppressed_but_the_feed_link_is_kept(): void
    {
        $this->publishPost('First');
        $id = BlogFrontPageBodyProvider::BODY_TOP;

        $withHeading = $this->provider()->renderFrontPageBody($id, $this->context(['showHeading' => true]))->html();
        $withoutHeading = $this->provider()->renderFrontPageBody($id, $this->context(['showHeading' => false]))->html();

        $this->assertStringContainsString('dixlase-blog-index__heading', $withHeading);
        $this->assertStringNotContainsString('dixlase-blog-index__heading', $withoutHeading);
        // The feed link is not part of the heading's job, and the body is the
        // only place a visitor finds the feed.
        $this->assertStringContainsString(route('dixlase-blog::front.feed'), $withoutHeading);
    }

    public function test_the_title_and_description_come_from_the_blog(): void
    {
        $this->publishPost('First');

        $result = $this->provider()->renderFrontPageBody(BlogFrontPageBodyProvider::BODY_TOP, $this->context());

        $this->assertSame(__('dixlase-blog::front.index.title'), $result->title);
        $this->assertSame(__('dixlase-blog::front.index.description'), $result->description);
    }

    // =========================================================================
    // The blog's own settings decide the listing
    // =========================================================================

    /**
     * $cardColumns reaches the body through a view composer keyed on the view
     * name. The body is a different view from front.index, so it had to be
     * added to that composer's list — without it the setting is silently
     * ignored, because the template defaults it.
     */
    public function test_the_single_column_setting_reaches_the_body(): void
    {
        $this->publishPost('First');
        app(BlogSettings::class)->save(['card_columns' => 'single']);

        $html = $this->provider()->renderFrontPageBody(BlogFrontPageBodyProvider::BODY_TOP, $this->context())->html();

        $this->assertStringContainsString('dixlase-blog-list--single', $html);
    }

    public function test_the_multi_column_setting_reaches_the_body(): void
    {
        $this->publishPost('First');
        app(BlogSettings::class)->save(['card_columns' => 'multi']);

        $html = $this->provider()->renderFrontPageBody(BlogFrontPageBodyProvider::BODY_TOP, $this->context())->html();

        $this->assertStringNotContainsString('dixlase-blog-list--single', $html);
    }

    public function test_the_sidebar_follows_the_blog_setting(): void
    {
        $this->publishPost('First');
        $id = BlogFrontPageBodyProvider::BODY_TOP;

        app(BlogSettings::class)->save(['sidebar_enabled' => true]);
        $this->assertStringContainsString(
            'dixlase-blog-sidebar',
            $this->provider()->renderFrontPageBody($id, $this->context())->html()
        );

        app(BlogSettings::class)->save(['sidebar_enabled' => false]);
        $this->assertStringNotContainsString(
            'dixlase-blog-sidebar',
            $this->provider()->renderFrontPageBody($id, $this->context())->html()
        );
    }

    public function test_the_full_text_layout_setting_reaches_the_body(): void
    {
        $this->publishPost('First');
        app(BlogSettings::class)->save(['index_layout' => 'full', 'latest_body' => 'full']);

        $html = $this->provider()->renderFrontPageBody(BlogFrontPageBodyProvider::BODY_TOP, $this->context())->html();

        $this->assertStringContainsString('dixlase-blog-full-list', $html);
    }

    // =========================================================================
    // Pagination
    // =========================================================================

    public function test_the_context_page_selects_the_page_of_posts(): void
    {
        // The sidebar's recent-posts widget names every post whatever the
        // page, so it is off here: this is about the listing. The sidebar has
        // its own test above.
        app(BlogSettings::class)->save(['posts_per_page' => 1, 'sidebar_enabled' => false]);
        // Titles that cannot collide with the pagination labels, which read
        // "Older posts" and "Newer posts" — asserting on "Older" matched the
        // link rather than the post while this test was being written.
        $this->publishPost('Walrus', Carbon::create(2026, 9, 1, 12));
        $this->publishPost('Zebra', Carbon::create(2026, 10, 1, 12));
        $id = BlogFrontPageBodyProvider::BODY_TOP;

        $first = $this->provider()->renderFrontPageBody($id, $this->context(['page' => 1]))->html();
        $second = $this->provider()->renderFrontPageBody($id, $this->context(['page' => 2]))->html();

        $this->assertStringContainsString('Zebra', $first);
        $this->assertStringNotContainsString('Walrus', $first);
        $this->assertStringContainsString('Walrus', $second);
        $this->assertStringNotContainsString('Zebra', $second);
    }

    public function test_the_page_is_reported_in_the_result_meta(): void
    {
        $this->publishPost('First');

        $result = $this->provider()->renderFrontPageBody(
            BlogFrontPageBodyProvider::BODY_TOP,
            $this->context(['page' => 3])
        );

        $this->assertSame(3, $result->meta['page']);
    }

    // =========================================================================
    // Assets
    // =========================================================================

    /**
     * A body cannot push onto the theme's stacks, so the asset URLs travel in
     * the result and the theme puts them where they belong.
     */
    /**
     * A body cannot push onto the theme's stacks, so the asset URLs travel in
     * the result and the theme puts them where they belong.
     *
     * The assertion is against what core's own helper yields rather than
     * against a fixed list, because whether any URL exists depends on a built
     * manifest being present — which it is on a site and is not in a bare
     * checkout. What must hold either way is that the result carries exactly
     * those URLs, and never a tag.
     */
    public function test_the_asset_urls_are_the_ones_cores_helper_yields(): void
    {
        $this->publishPost('First');

        $result = $this->provider()->renderFrontPageBody(BlogFrontPageBodyProvider::BODY_TOP, $this->context());
        $html = load_plugin_assets('DixlaseBlog', BlogFrontAssetUrls::FILES);

        preg_match_all('/<link\b[^>]*\brel="stylesheet"[^>]*\bhref="([^"]*)"/i', $html, $styles);
        preg_match_all('/<script\b[^>]*\bsrc="([^"]*)"/i', $html, $scripts);

        $this->assertSame(array_values($styles[1]), $result->styleUrls);
        $this->assertSame(array_values($scripts[1]), $result->scriptUrls);

        foreach ([...$result->styleUrls, ...$result->scriptUrls] as $url) {
            $this->assertStringNotContainsString('<', $url, 'a tag was handed over where a URL was expected');
        }
    }

    /**
     * The URL list is read back out of core's load_plugin_assets(), which also
     * emits <link rel="preload" as="font"> for a font an entry pulls in. A
     * preload handed over as a stylesheet would be rendered as one.
     */
    public function test_a_font_preload_is_not_taken_for_a_stylesheet(): void
    {
        $html = '<link rel="stylesheet" href="/style.css">'
            .'<link rel="preload" as="font" href="/font.woff2" type="font/woff2" crossorigin="anonymous">'
            .'<script type="module" src="/front.js" nonce="abc"></script>';

        $resolver = new BlogFrontAssetUrls();
        $reflection = new \ReflectionClass(BlogFrontAssetUrls::class);

        $extracted = [
            'styleUrls' => $reflection->getMethod('stylesheetHrefs')->invoke($resolver, $html),
            'scriptUrls' => $reflection->getMethod('scriptSources')->invoke($resolver, $html),
        ];

        $this->assertSame(['/style.css'], $extracted['styleUrls']);
        $this->assertSame(['/front.js'], $extracted['scriptUrls']);
    }

    /**
     * The entries the resolver asks for must stay the ones the blog's own
     * pages load, or the front page body is styled by a different build.
     */
    public function test_the_asset_entries_match_the_blogs_own_assets_partial(): void
    {
        $partial = File::get(base_path('plugins/DixlaseBlog/resources/views/front/_assets.blade.php'));

        foreach (BlogFrontAssetUrls::FILES as $file) {
            $this->assertStringContainsString($file, $partial);
        }
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * A theme finds the provider through the capability tag, so a provider
     * that is never tagged offers nothing however well it renders.
     */
    public function test_the_provider_is_reachable_through_the_capability_tag(): void
    {
        $results = app(PluginServiceResolver::class)->resolveAll(FrontPageBodyProviderInterface::class);

        $slugs = array_map(fn ($result) => $result->pluginSlug, array_filter(
            $results,
            fn ($result) => $result->isResolved()
        ));

        $this->assertContains(BlogPost::PLUGIN_SLUG, $slugs);
    }

    public function test_the_label_is_translated(): void
    {
        $this->assertSame(__('dixlase-blog::admin.front_page_body.label'), $this->provider()->getFrontPageBodyLabel());
        $this->assertStringNotContainsString('dixlase-blog::', $this->provider()->getFrontPageBodyLabel());
    }
}
