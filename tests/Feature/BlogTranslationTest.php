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
use App\Helpers\LocaleHelper;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFeedController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Tests\TestCase;

/**
 * Posts, category names and tag names are translated through the core
 * TranslationResolver contract
 * (bound by DixlaseMultilingual). A stand-in resolver keeps these tests
 * independent of that plugin.
 */
class BlogTranslationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string|null> Overlays keyed by "id|locale|field". */
    private array $overlays = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        Route::name('dixlase-blog::front.')->group(function () {
            Route::get('/blog', [DixlaseBlogFrontController::class, 'index'])->name('index');
            Route::get('/blog/feed.xml', [DixlaseBlogFeedController::class, 'feed'])->name('feed');
            Route::get('/blog/category/{slug}', [DixlaseBlogFrontController::class, 'category'])->name('category');
            Route::get('/blog/tag/{slug}', [DixlaseBlogFrontController::class, 'tag'])->name('tag');
            Route::get('/blog/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('show');
        });
        // Routes added after boot are not in the name index until it is rebuilt.
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    public function test_source_text_is_shown_without_a_translation_resolver(): void
    {
        $this->assertFalse(App::bound(TranslationResolver::class));

        $post = $this->makePost('ja');
        App::setLocale('en');

        $this->assertSame('日本語のタイトル', $post->localized('title'));
        $this->assertSame('ja', $post->localizedLang('title'));
    }

    public function test_translation_is_shown_for_the_current_locale(): void
    {
        $this->bindResolver();
        $post = $this->makePost('ja');
        $this->translate($post, 'en', ['title' => 'English title', 'content' => 'English body']);

        App::setLocale('en');
        $this->assertSame('English title', $post->localized('title'));
        $this->assertSame('en', $post->localizedLang('title'));
        $this->assertSame('English body', $post->localized('content'));

        App::setLocale('ja');
        $this->assertSame('日本語のタイトル', $post->fresh()->localized('title'));
    }

    public function test_missing_or_empty_translation_falls_back_to_the_source_text(): void
    {
        $this->bindResolver();
        $post = $this->makePost('ja');
        $this->translate($post, 'en', ['title' => '   ']);

        App::setLocale('en');
        $this->assertSame('日本語のタイトル', $post->localized('title'));
        $this->assertSame('ja', $post->localizedLang('title'));
        $this->assertSame('日本語の本文', $post->localized('content'));
        $this->assertSame('ja', $post->localizedLang('content'));
    }

    public function test_translated_content_without_an_excerpt_derives_one(): void
    {
        $this->bindResolver();
        $post = $this->makePost('ja');
        $this->translate($post, 'en', ['content' => '## Heading **bold** text']);

        App::setLocale('en');
        $this->assertSame('Heading bold text', $post->localized('excerpt'));
        $this->assertSame('en', $post->localizedLang('excerpt'));
    }

    public function test_raw_attributes_keep_the_source_text(): void
    {
        $this->bindResolver();
        $post = $this->makePost('ja');
        $this->translate($post, 'en', ['title' => 'English title']);

        App::setLocale('en');
        $this->assertSame('日本語のタイトル', $post->title);
    }

    public function test_post_without_lang_uses_the_site_default_locale(): void
    {
        $post = $this->makePost(null);

        $this->assertSame(LocaleHelper::getSiteDefaultLocale(), $post->sourceLocale());
    }

    public function test_new_post_defaults_to_the_site_default_locale(): void
    {
        $post = app(BlogPostService::class)->create([
            'title' => 'Hello',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
        ], null);

        $this->assertSame(LocaleHelper::getSiteDefaultLocale(), $post->lang);
    }

    public function test_feed_shows_translations_for_the_locale(): void
    {
        $this->bindResolver();
        $post = $this->makePost('ja');
        $this->translate($post, 'en', ['title' => 'English title', 'excerpt' => 'English excerpt']);
        $this->makePost('ja', 'untranslated-post', 'まだ訳していない記事');

        App::setLocale('en');
        $response = $this->get('/blog/feed.xml');

        $response->assertOk();
        $response->assertSee('<language>en</language>', false);
        $response->assertSee('English title', false);
        $response->assertSee('English excerpt', false);
        $response->assertSee('まだ訳していない記事', false);
        $response->assertDontSee('日本語のタイトル', false);
    }

    public function test_manifest_declares_the_translatable_types(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('plugins/DixlaseBlog/plugin.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertContains('multilingual-content', $manifest['capabilities']);

        $types = array_column($manifest['multilingual_content']['types'], null, 'key');
        $this->assertSame(BlogPost::class, $types['dixlase-blog:post']['model']);
        $this->assertSame(['title', 'excerpt', 'content'], array_column($types['dixlase-blog:post']['fields'], 'name'));
        $this->assertSame(BlogCategory::class, $types['dixlase-blog:category']['model']);
        $this->assertSame(['label'], array_column($types['dixlase-blog:category']['fields'], 'name'));
        $this->assertSame(BlogTag::class, $types['dixlase-blog:tag']['model']);
        $this->assertSame(['name'], array_column($types['dixlase-blog:tag']['fields'], 'name'));
    }

    public function test_category_and_tag_names_are_translated_with_shared_slugs(): void
    {
        $this->bindResolver();
        $category = BlogCategory::create(['label' => 'お知らせ', 'slug' => 'news', 'lang' => 'ja', 'depth' => 0, 'display_order' => 0]);
        $tag = BlogTag::create(['name' => '日本語タグ', 'slug' => 'release', 'lang' => 'ja']);
        $this->overlays[$category->getKey().'|en|label'] = 'News';

        App::setLocale('en');
        $this->assertSame('News', $category->localized('label'));
        $this->assertSame('en', $category->localizedLang('label'));
        $this->assertSame('日本語タグ', $tag->localized('name'));
        $this->assertSame('ja', $tag->localizedLang('name'));
        $this->assertSame('お知らせ', $category->label);

        App::setLocale('ja');
        $this->assertSame('お知らせ', $category->fresh()->localized('label'));
    }

    public function test_category_or_tag_without_lang_uses_the_site_default_locale(): void
    {
        $category = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'depth' => 0, 'display_order' => 0]);

        $this->assertSame(LocaleHelper::getSiteDefaultLocale(), $category->sourceLocale());
    }

    public function test_new_tag_takes_the_language_of_its_post(): void
    {
        $post = app(BlogPostService::class)->create([
            'title' => '日本語の記事',
            'content' => '本文',
            'editor_type' => 'markdown',
            'status' => 'draft',
            'lang' => 'ja',
            'tags' => ['お知らせ'],
        ], null);

        $this->assertSame('ja', $post->tags->first()?->lang);
    }

    private function makePost(?string $lang, string $slug = 'hello', string $title = '日本語のタイトル'): BlogPost
    {
        return BlogPost::create([
            'title' => $title,
            'slug' => $slug,
            'content' => '日本語の本文',
            'excerpt' => null,
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => $lang,
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function translate(BlogPost $post, string $locale, array $fields): void
    {
        foreach ($fields as $field => $value) {
            $this->overlays[$post->getKey().'|'.$locale.'|'.$field] = $value;
        }
    }

    private function bindResolver(): void
    {
        $overlays = &$this->overlays;

        $this->app->instance(TranslationResolver::class, new class($overlays) implements TranslationResolver
        {
            /** @param array<string, string|null> $overlays */
            public function __construct(private array &$overlays)
            {
            }

            public function resolve(Model $model, string $field, string $locale): mixed
            {
                return $this->overlays[$model->getKey().'|'.$locale.'|'.$field] ?? null;
            }

            public function store(Model $model, string $field, mixed $value, string $locale): void
            {
                $this->overlays[$model->getKey().'|'.$locale.'|'.$field] = $value;
            }

            public function all(Model $model, string $field): array
            {
                return [];
            }

            public function exists(Model $model, string $field, string $locale): bool
            {
                return array_key_exists($model->getKey().'|'.$locale.'|'.$field, $this->overlays);
            }

            public function delete(Model $model, string $field, ?string $locale = null): void
            {
            }

            public function getAvailableLocales(Model $model): array
            {
                return [];
            }

            public function copy(Model $source, Model $target, ?array $fields = null, ?array $locales = null): void
            {
            }
        });
    }
}
