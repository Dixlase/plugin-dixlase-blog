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
use App\Enums\MemberRole;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Plugins\DixlaseBlog\App\Services\BlogPostSourceLanguage;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The language a post is written in: where a new post starts, and changing
 * it on a saved one, which keeps the text and moves the labels (#133).
 */
class BlogPostSourceLanguageTest extends TestCase
{
    use RefreshDatabase;

    private string $themeDirectory;

    private FakeTranslationResolver $translations;

    private Member $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));
        $this->app['view']->addNamespace('admin', resource_path('views/'.config('themes.admin_theme', 'admin')));
        $this->app['view']->addNamespace('components', resource_path('views/components'));
        $this->app['view']->addNamespace('layouts', resource_path('views/layouts'));

        $this->themeDirectory = storage_path('framework/testing/blog-source-language-'.uniqid());
        File::ensureDirectoryExists($this->themeDirectory.'/layouts');
        File::put($this->themeDirectory.'/layouts/preview.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->replaceNamespace('themes', [$this->themeDirectory]);

        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware([CheckInstallationReady::class, CheckMaintenanceMode::class]);

        $this->translations = new FakeTranslationResolver();
        $this->app->instance(TranslationResolver::class, $this->translations);

        $this->author = Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]);
        $this->actingAs($this->author);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themeDirectory);

        parent::tearDown();
    }

    public function test_a_new_post_starts_in_the_admins_language(): void
    {
        $this->multilingual();

        foreach (['ja', 'en'] as $locale) {
            app()->setLocale($locale);

            $this->get(route('dixlase-blog::admin.blog.create'))->assertOk()
                ->assertViewHas('langValue', $locale);
        }
    }

    public function test_a_saved_post_offers_its_language_on_a_multilingual_site(): void
    {
        $post = $this->createPost('en');
        $this->translations->set($post, 'ja', ['title' => 'Translated']);

        $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $post->id]))->assertOk()
            ->assertDontSee('name="lang"', false);

        $this->multilingual();

        $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $post->id]))->assertOk()
            ->assertSee('name="lang"', false)
            ->assertSee('x-data="blogSourceLanguage"', false)
            ->assertSee('data-current="en"', false)
            ->assertSee('data-translated="ja"', false)
            ->assertSee('name="confirm_lang_change"', false);
    }

    public function test_changing_the_language_keeps_the_text_and_moves_the_translation(): void
    {
        $this->multilingual();
        // Written in Japanese but saved as English; the English text went
        // in as the Japanese translation -- the case in #133.
        $post = $this->createPost('en', '日本語のタイトル', '日本語の本文');
        $this->translations->set($post, 'ja', ['title' => 'English title', 'content' => 'English body']);

        $this->update($post, ['lang' => 'ja'])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame('ja', $post->lang);
        $this->assertSame('日本語のタイトル', $post->title);
        $this->assertSame('日本語の本文', $post->content);
        $this->assertSame('English title', $this->translations->resolve($post, 'title', 'en'));
        $this->assertSame('English body', $this->translations->resolve($post, 'content', 'en'));
        $this->assertFalse($this->translations->exists($post, 'title', 'ja'));
        $this->assertFalse($this->translations->exists($post, 'content', 'ja'));
    }

    public function test_changing_to_a_language_with_no_translation_needs_confirmation(): void
    {
        $this->multilingual();
        $post = $this->createPost('en');

        $this->update($post, ['lang' => 'ja'])->assertSessionHasErrors('lang');
        $this->assertSame('en', $post->refresh()->lang);

        $this->update($post, ['lang' => 'ja', 'confirm_lang_change' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('ja', $post->refresh()->lang);
        $this->assertSame('Title', $post->title);
    }

    public function test_keeping_the_language_needs_no_confirmation(): void
    {
        $this->multilingual();
        $post = $this->createPost('en');

        $this->update($post, ['lang' => 'en', 'title' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $post->refresh()->title);
    }

    public function test_the_moved_fields_are_the_ones_plugin_json_declares(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path('plugins/DixlaseBlog/plugin.json')), true);
        $type = collect($manifest['multilingual_content']['types'])->firstWhere('key', 'dixlase-blog:post');

        $this->assertSame(array_column($type['fields'], 'name'), BlogPostSourceLanguage::TRANSLATABLE_FIELDS);
    }

    private function multilingual(): void
    {
        config(['dixlase_multilingual.locale_url_routing_enabled' => true]);
    }

    private function createPost(string $lang, string $title = 'Title', string $content = 'Body'): BlogPost
    {
        return app(BlogPostService::class)->create([
            'title' => $title,
            'content' => $content,
            'editor_type' => 'markdown',
            'status' => 'draft',
            'lang' => $lang,
        ], $this->author->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(BlogPost $post, array $overrides): TestResponse
    {
        return $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $overrides + [
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'editor_type' => 'markdown',
            'status' => 'draft',
        ]);
    }
}
