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
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * Month and day archives (/blog/{year}/{month}[/{day}]) and the blog
 * search (/blog/search?q=).
 */
class BlogDateArchiveAndSearchTest extends TestCase
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

        // A bare layout in a throwaway directory stands in for the theme.
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

    public function test_a_month_archive_lists_that_months_visible_posts(): void
    {
        $this->makePost('September post', Carbon::create(2026, 9, 30, 23, 59));
        $this->makePost('October first', Carbon::create(2026, 10, 1, 0, 0));
        $this->makePost('October tenth', Carbon::create(2026, 10, 10, 9));
        $this->makePost('October draft', Carbon::create(2026, 10, 5), ContentStatus::DRAFT);
        $this->makePost('October future', Carbon::create(2026, 10, 25));

        $this->get('/blog/2026/10')->assertOk()
            ->assertSee('October 2026')
            ->assertSeeInOrder(['October tenth', 'October first'])
            ->assertDontSee('September post')
            ->assertDontSee('October draft')
            ->assertDontSee('October future');
    }

    public function test_a_day_archive_lists_that_days_posts(): void
    {
        $this->makePost('Morning post', Carbon::create(2026, 10, 10, 0, 0));
        $this->makePost('Night post', Carbon::create(2026, 10, 10, 23, 59, 59));
        $this->makePost('Next day post', Carbon::create(2026, 10, 11, 0, 0));

        $this->get('/blog/2026/10/10')->assertOk()
            ->assertSee('October 10, 2026')
            ->assertSee('Morning post')
            ->assertSee('Night post')
            ->assertDontSee('Next day post');
    }

    public function test_impossible_dates_are_not_found(): void
    {
        $this->get('/blog/2026/02/30')->assertNotFound();
        $this->get('/blog/2026/13')->assertNotFound();
        $this->get('/blog/2026/1')->assertNotFound();
    }

    public function test_an_empty_month_says_so(): void
    {
        $this->get('/blog/2025/01')->assertOk()->assertSee(__('dixlase-blog::front.archive.no_posts'));
    }

    public function test_search_finds_every_word_in_title_excerpt_or_body(): void
    {
        $this->makePost('Release notes', Carbon::create(2026, 10, 1), body: 'The new editor arrives.');
        $this->makePost('Editor tips', Carbon::create(2026, 10, 2), body: 'Shortcuts for writing.');
        $this->makePost('Unrelated', Carbon::create(2026, 10, 3), body: 'Nothing to see.');

        $this->get('/blog/search?q=editor')->assertOk()
            ->assertSee('Release notes')
            ->assertSee('Editor tips')
            ->assertDontSee('Unrelated');

        // Every word must match.
        $this->get('/blog/search?q='.urlencode('editor shortcuts'))->assertOk()
            ->assertSee('Editor tips')
            ->assertDontSee('Release notes');
    }

    public function test_search_splits_on_full_width_spaces(): void
    {
        $this->makePost('ブログを始めましょう', Carbon::create(2026, 10, 1), body: 'サンプル記事です。');
        $this->makePost('別の記事', Carbon::create(2026, 10, 2), body: 'ブログの話です。');

        $this->get('/blog/search?q='.urlencode('ブログ　サンプル'))->assertOk()
            ->assertSee('ブログを始めましょう')
            ->assertDontSee('別の記事');
    }

    public function test_search_treats_wildcards_as_text(): void
    {
        $this->makePost('Half off', Carbon::create(2026, 10, 1), body: 'Now 50% off.');
        $this->makePost('Snake_case names', Carbon::create(2026, 10, 2));
        $this->makePost('Plain post', Carbon::create(2026, 10, 3));

        $this->get('/blog/search?q='.urlencode('%'))->assertOk()
            ->assertSee('Half off')
            ->assertDontSee('Plain post');
        $this->get('/blog/search?q='.urlencode('_'))->assertOk()
            ->assertSee('Snake_case names')
            ->assertDontSee('Plain post');
    }

    public function test_search_skips_posts_that_are_not_visible(): void
    {
        $this->makePost('Draft about editors', Carbon::create(2026, 10, 1), ContentStatus::DRAFT);
        $this->makePost('Future about editors', Carbon::create(2026, 10, 30));

        $this->get('/blog/search?q=editors')->assertOk()
            ->assertSee(__('dixlase-blog::front.search.no_results'))
            ->assertDontSee('Draft about editors')
            ->assertDontSee('Future about editors');
    }

    public function test_search_results_are_kept_out_of_search_engines(): void
    {
        $this->get('/blog/search?q=anything')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_an_empty_search_shows_the_form_only(): void
    {
        $this->makePost('Some post', Carbon::create(2026, 10, 1));

        $this->get('/blog/search?q=')->assertOk()
            ->assertSee('name="q"', false)
            ->assertDontSee('Some post')
            ->assertDontSee(__('dixlase-blog::front.search.no_results'));
    }

    public function test_search_keeps_the_query_in_pagination_links(): void
    {
        foreach (range(1, 13) as $n) {
            $this->makePost('Match '.$n, Carbon::create(2026, 10, $n));
        }

        $this->get('/blog/search?q=match')->assertOk()->assertSee('q=match', false);
    }

    private function makePost(string $title, Carbon $publishedAt, ContentStatus $status = ContentStatus::PUBLISHED, string $body = 'Body'): BlogPost
    {
        return BlogPost::create([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title) ?: 'post-'.uniqid(),
            'content' => $body,
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => $status,
            'lang' => 'en',
            'published_at' => $publishedAt,
        ]);
    }
}
