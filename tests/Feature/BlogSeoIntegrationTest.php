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

use App\Contracts\PluginIntegration\SeoMetaProviderInterface;
use App\Contracts\TranslationResolver;
use App\DTO\PluginIntegration\SeoMetaDTO;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Http\Middleware\SetFrontLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogLocalizedUrlProvider;
use Plugins\DixlaseBlog\App\Services\BlogPostLinkableProvider;
use Plugins\DixlaseBlog\App\Services\BlogSeoMeta;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * DixlaseSEO integration through core contracts only: SEO meta storage
 * (SeoMetaProviderInterface, mocked here), the sitemap's linkable
 * provider and the hreflang localized-URL provider. Without DixlaseSEO
 * there is no binding, and everything degrades to a no-op.
 */
class BlogSeoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
    }

    public function test_seo_section_is_off_without_dixlase_seo(): void
    {
        $this->assertFalse($this->app->has(SeoMetaProviderInterface::class));

        $data = app(BlogSeoMeta::class)->formData($this->makePost());

        $this->assertFalse($data['seoMetaEnabled']);
        $this->assertNull($data['seoMeta']);
    }

    public function test_seo_section_is_off_when_the_blog_integration_is_switched_off(): void
    {
        $provider = $this->bindSeo(enabled: false);
        $provider->shouldNotReceive('getMeta');

        $this->assertFalse(app(BlogSeoMeta::class)->formData($this->makePost())['seoMetaEnabled']);
    }

    public function test_form_data_reads_the_posts_meta(): void
    {
        $post = $this->makePost();
        $meta = new SeoMetaDTO(description: 'Stored');
        $this->bindSeo()->shouldReceive('getMeta')->with('dixlase-blog', (string) $post->id)->andReturn($meta);

        $data = app(BlogSeoMeta::class)->formData($post);

        $this->assertTrue($data['seoMetaEnabled']);
        $this->assertSame($meta, $data['seoMeta']);
    }

    public function test_save_stores_the_submitted_meta(): void
    {
        $post = $this->makePost();
        $this->bindSeo()->shouldReceive('saveMeta')->once()->with(
            'dixlase-blog',
            (string) $post->id,
            Mockery::on(fn (SeoMetaDTO $dto) => $dto->description === 'About' && $dto->ogpMediaId === null),
        );

        app(BlogSeoMeta::class)->save($post, ['seo_meta' => ['description' => 'About', 'ogp_media_id' => null]]);
        $this->addToAssertionCount(1);
    }

    public function test_save_leaves_the_meta_alone_when_the_form_had_no_seo_fields(): void
    {
        $this->bindSeo()->shouldNotReceive('saveMeta');

        app(BlogSeoMeta::class)->save($this->makePost(), ['title' => 'x']);
        $this->addToAssertionCount(1);
    }

    public function test_force_delete_removes_the_meta_but_soft_delete_keeps_it(): void
    {
        $post = $this->makePost();
        $provider = $this->bindSeo();
        $provider->shouldReceive('deleteMeta')->once()->with('dixlase-blog', (string) $post->id);

        $post->delete();
        $post->forceDelete();
        $this->addToAssertionCount(1);
    }

    public function test_seo_description_falls_back_to_the_excerpt(): void
    {
        $post = $this->makePost();

        $this->assertSame('日本語の抜粋', $post->getTranslation('seo_description', 'ja', false));
    }

    public function test_seo_description_uses_the_stored_text_then_translations(): void
    {
        $post = $this->makePost();
        $this->bindSeo()->shouldReceive('getMeta')->andReturn(new SeoMetaDTO(description: '保存した説明'));
        $translations = new FakeTranslationResolver();
        $this->app->instance(TranslationResolver::class, $translations);

        $this->assertSame('保存した説明', $post->getTranslation('seo_description', 'ja', false));
        // No English description yet: the excerpt in English, else the source excerpt.
        $this->assertSame('日本語の抜粋', $post->getTranslation('seo_description', 'en', false));

        $translations->set($post, 'en', ['excerpt' => 'English excerpt']);
        $this->assertSame('English excerpt', $post->fresh()->getTranslation('seo_description', 'en', false));

        $translations->set($post, 'en', ['seo_description' => 'English description']);
        $this->assertSame('English description', $post->fresh()->getTranslation('seo_description', 'en', false));
    }

    public function test_linkable_provider_lists_visible_posts_with_locale_less_urls(): void
    {
        $visible = $this->makePost();
        $this->makePost('draft-post', ContentStatus::DRAFT);

        $items = app(BlogPostLinkableProvider::class)->getAvailableItems();

        $this->assertCount(1, $items);
        $this->assertSame((string) $visible->id, $items[0]->id);
        $this->assertSame('/blog/hello', $items[0]->url);
        $this->assertSame('ja', $items[0]->locale);
        $this->assertSame($visible->updated_at?->toIso8601String(), $items[0]->meta['updated_at']);
        $this->assertNull(app(BlogPostLinkableProvider::class)->getItemById((string) ($visible->id + 1)));
        $this->assertCount(1, app(BlogPostLinkableProvider::class)->searchItems('タイトル'));
    }

    public function test_localized_url_provider_offers_every_locale_for_a_blog_page(): void
    {
        Route::prefix('{locale}')->where(['locale' => 'ja|en'])->middleware(SetFrontLocale::class)
            ->get('/blog/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('dixlase-blog::front.show');
        Route::getRoutes()->refreshNameLookups();

        $request = Request::create('/ja/blog/hello');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request)->bind($request));

        $urls = app(BlogLocalizedUrlProvider::class)->getAlternateUrls($request);

        $this->assertSame(url('/ja/blog/hello'), $urls['ja']);
        $this->assertSame(url('/en/blog/hello'), $urls['en']);
    }

    public function test_localized_url_provider_ignores_other_routes(): void
    {
        Route::get('/elsewhere', fn () => 'x')->name('other.page');
        Route::getRoutes()->refreshNameLookups();

        $request = Request::create('/elsewhere');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request)->bind($request));

        $this->assertSame([], app(BlogLocalizedUrlProvider::class)->getAlternateUrls($request));
    }

    private function bindSeo(bool $enabled = true): Mockery\MockInterface
    {
        $provider = Mockery::mock(SeoMetaProviderInterface::class);
        $provider->shouldReceive('isEnabledForPlugin')->with('dixlase-blog')->andReturn($enabled);
        $this->app->instance(SeoMetaProviderInterface::class, $provider);

        return $provider;
    }

    private function makePost(string $slug = 'hello', ContentStatus $status = ContentStatus::PUBLISHED): BlogPost
    {
        return BlogPost::create([
            'title' => '日本語のタイトル',
            'slug' => $slug,
            'content' => '日本語の本文',
            'excerpt' => '日本語の抜粋',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => $status,
            'lang' => 'ja',
            'published_at' => now()->subDay(),
        ]);
    }
}
