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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The share buttons under a post, and their settings.
 */
class BlogShareButtonsTest extends TestCase
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
        app()->setLocale('en');

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

        BlogPost::create([
            'title' => 'Hello & welcome',
            'slug' => 'hello',
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => ContentStatus::PUBLISHED,
            'lang' => 'en',
            'published_at' => now()->subDay(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_every_share_button_shows_by_default(): void
    {
        $url = rawurlencode(url('/blog/hello'));

        $this->get('/blog/hello')->assertOk()
            ->assertSee('href="https://x.com/intent/post?url='.$url.'&amp;text=Hello%20%26%20welcome"', false)
            ->assertSee('href="https://www.facebook.com/sharer/sharer.php?u='.$url.'"', false)
            ->assertSee('href="https://social-plugins.line.me/lineit/share?url='.$url.'"', false)
            ->assertSee('data-dixlase-blog-copy="'.url('/blog/hello').'"', false);
    }

    public function test_service_links_open_apart_from_the_page(): void
    {
        $this->get('/blog/hello')->assertOk()
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_only_the_chosen_services_show(): void
    {
        app(BlogSettings::class)->save(['share_services' => ['line', 'copy']]);

        $this->get('/blog/hello')->assertOk()
            ->assertSee('dixlase-blog-share__button--line', false)
            ->assertSee('dixlase-blog-share__button--copy', false)
            ->assertDontSee('dixlase-blog-share__button--x', false)
            ->assertDontSee('dixlase-blog-share__button--facebook', false);
    }

    public function test_no_service_hides_the_row(): void
    {
        app(BlogSettings::class)->save(['share_services' => []]);

        $this->get('/blog/hello')->assertOk()->assertDontSee('dixlase-blog-share', false);
    }

    public function test_the_settings_screen_saves_the_switches_in_a_fixed_order(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        $this->put(route('dixlase-blog::admin.blog.settings.update'), [
            'share_services' => ['copy' => '1', 'x' => '1', 'facebook' => '0', 'line' => '0', 'unknown' => '1'],
        ])->assertSessionHasNoErrors();

        app()->forgetInstance(BlogSettings::class);
        $this->assertSame(['x', 'copy'], app(BlogSettings::class)->shareServices());
    }

    public function test_every_service_has_labels(): void
    {
        foreach (BlogSettings::SHARE_SERVICES as $key) {
            $this->assertNotSame('dixlase-blog::admin.settings.share_service_'.$key, __('dixlase-blog::admin.settings.share_service_'.$key));
            $this->assertNotSame('dixlase-blog::front.share.'.$key, __('dixlase-blog::front.share.'.$key));
        }
    }
}
