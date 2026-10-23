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
use App\Services\Auth\SignedInMemberResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The post page hands core's admin bar a link to the post's edit screen,
 * for a member signed in to the admin who may edit posts.
 */
class BlogAdminBarEditLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $layoutDirectory;

    private BlogPost $post;

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

        // A bare layout that prints the admin bar's sections the way core
        // reads them.
        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put($this->layoutDirectory.'/layouts/app.blade.php', "<html><body><nav>[@yield('admin_bar_edit_url')|@yield('admin_bar_edit_label')]</nav>@yield('content')</body></html>");
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        $this->post = BlogPost::create([
            'title' => 'A post',
            'slug' => 'a-post',
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

    public function test_an_editor_signed_in_to_the_admin_gets_the_edit_link(): void
    {
        $this->signInToTheAdmin(MemberRole::EDITOR);

        $this->get('/blog/a-post')->assertOk()
            ->assertSee('<nav>['.route('dixlase-blog::admin.blog.edit', ['post' => $this->post->id]).'|Edit this post]</nav>', false);
    }

    public function test_a_member_who_may_not_edit_posts_gets_no_link(): void
    {
        $this->signInToTheAdmin(MemberRole::CONTRIBUTOR);

        $this->get('/blog/a-post')->assertOk()->assertSee('<nav>[|]</nav>', false);
    }

    public function test_a_visitor_gets_no_link(): void
    {
        $this->signInToTheAdmin(null);

        $this->get('/blog/a-post')->assertOk()->assertSee('<nav>[|]</nav>', false);
    }

    /**
     * The front request carries no admin login, so core's resolver is
     * what reports one; stand in for it.
     */
    private function signInToTheAdmin(?MemberRole $role): void
    {
        $member = $role === null ? null : Member::factory()->create(['role' => $role, 'status' => 1]);

        $this->app->instance(SignedInMemberResolver::class, new class($member) extends SignedInMemberResolver
        {
            public function __construct(private readonly ?Member $member)
            {
            }

            public function resolve(): ?Member
            {
                return $this->member;
            }
        });
    }
}
