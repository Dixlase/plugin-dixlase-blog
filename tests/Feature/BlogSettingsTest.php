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
        $this->assertSame(12, app(BlogSettings::class)->postsPerPage());
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
            ->assertRedirect(route('dixlase-blog::admin.blog.settings.edit'));

        $this->assertSame('5', DB::table('plg_dixlase_blog_settings')->where('name', 'posts_per_page')->value('value'));
        $this->assertSame(5, app(BlogSettings::class)->postsPerPage());
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
}
