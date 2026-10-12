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
use Illuminate\Testing\TestResponse;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * Breadcrumbs and their BreadcrumbList structured data on every public
 * blog page.
 */
class BlogBreadcrumbsTest extends TestCase
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

        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 10, 20, 12));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_a_post_goes_through_its_category_and_parents(): void
    {
        $parent = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 0]);
        $child = BlogCategory::create(['label' => 'Releases', 'slug' => 'releases', 'parent_id' => $parent->id, 'display_order' => 0]);
        $this->makePost('Version one', 'version-one')->categories()->attach($child->id);

        $response = $this->get('/blog/version-one')->assertOk();

        $this->assertSame(
            [['Home', url('/')], ['Blog', url('/blog')], ['News', url('/blog/category/news')], ['Releases', url('/blog/category/releases')], ['Version one', url('/blog/version-one')]],
            $this->trail($response),
        );
        $response->assertSee('<span aria-current="page" lang="en">Version one</span>', false);
    }

    public function test_a_post_without_categories_sits_under_the_blog(): void
    {
        $this->makePost('Plain', 'plain');

        $this->assertSame(['Home', 'Blog', 'Plain'], array_column($this->trail($this->get('/blog/plain')), 0));
    }

    public function test_archives_and_search_have_their_own_trail(): void
    {
        $this->makePost('Plain', 'plain')->tags()->attach(BlogTag::create(['name' => 'Release', 'slug' => 'release'])->id);
        BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 0]);

        $this->assertSame(['Home', 'Blog'], array_column($this->trail($this->get('/blog')), 0));
        $this->assertSame(['Home', 'Blog', 'News'], array_column($this->trail($this->get('/blog/category/news')), 0));
        $this->assertSame(['Home', 'Blog', '#Release'], array_column($this->trail($this->get('/blog/tag/release')), 0));
        $this->assertSame(['Home', 'Blog', 'October 2026'], array_column($this->trail($this->get('/blog/2026/10')), 0));
        $this->assertSame(
            [['October 2026', url('/blog/2026/10')], ['October 9, 2026', url('/blog/2026/10/09')]],
            array_slice($this->trail($this->get('/blog/2026/10/09')), 2),
        );
        $this->assertSame(['Home', 'Blog', 'Search'], array_column($this->trail($this->get('/blog/search?q=plain')), 0));
    }

    public function test_a_title_cannot_break_out_of_the_structured_data(): void
    {
        $this->makePost('</script><script>alert(1)</script>', 'tricky');

        $response = $this->get('/blog/tricky')->assertOk();

        $this->assertSame('</script><script>alert(1)</script>', $this->trail($response)[2][0]);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * The trail as [name, url] pairs, read from the structured data.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function trail(TestResponse $response): array
    {
        $response->assertOk();
        $this->assertSame(1, preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', (string) $response->getContent(), $match));
        $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('BreadcrumbList', $data['@type']);
        $this->assertSame(range(1, count($data['itemListElement'])), array_column($data['itemListElement'], 'position'));

        return array_map(fn (array $item) => [$item['name'], $item['item']], $data['itemListElement']);
    }

    private function makePost(string $title, string $slug): BlogPost
    {
        return BlogPost::create([
            'title' => $title,
            'slug' => $slug,
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'en',
            'published_at' => \Illuminate\Support\Carbon::create(2026, 10, 9, 9),
        ]);
    }
}
