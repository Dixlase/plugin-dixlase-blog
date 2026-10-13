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

use App\Enums\MemberRole;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Tests\TestCase;

/**
 * Every admin screen (each GET route in routes/admin.php) opens without a
 * server error, for an ADMIN and for an EDITOR, with real posts,
 * categories, tags, revisions and a trashed post in place. Routes are
 * read from the route file, so a screen added later is covered without
 * touching this test.
 */
class BlogAdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private BlogPost $post;

    private BlogPostRevision $revision;

    private string $themeDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));
        $this->app['view']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/resources/views'));

        // AppServiceProvider registers these after a database query, which
        // fails while the test app boots before migrating; core's own admin
        // tests register them the same way.
        $this->app['view']->addNamespace('admin', resource_path('views/'.config('themes.admin_theme', 'admin')));
        $this->app['view']->addNamespace('components', resource_path('views/components'));
        $this->app['view']->addNamespace('layouts', resource_path('views/layouts'));

        // The preview frame extends the theme's preview layout. A bare one in
        // a throwaway directory stands in for the theme.
        $this->themeDirectory = storage_path('framework/testing/blog-admin-theme-'.uniqid());
        File::ensureDirectoryExists($this->themeDirectory.'/layouts');
        File::put($this->themeDirectory.'/layouts/preview.blade.php', '<html><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->replaceNamespace('themes', [$this->themeDirectory]);

        // The admin screens link to the public pages, so the front routes too.
        Route::middleware('web')->group(base_path('plugins/DixlaseBlog/routes/web.php'));
        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        $author = Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]);
        $service = app(BlogPostService::class);

        $category = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'lang' => 'en', 'display_order' => 10]);
        BlogCategory::create(['label' => 'Releases', 'slug' => 'releases', 'lang' => 'en', 'parent_id' => $category->id, 'display_order' => 10]);

        $this->post = $service->create([
            'title' => 'Hello',
            'content' => "# Hello\n\nBody",
            'editor_type' => 'markdown',
            'status' => 'published',
            'categories' => [$category->id],
            'tags' => ['release'],
        ], $author->id);
        $service->update($this->post, [
            'title' => 'Hello again',
            'content' => "# Hello\n\nEdited body",
            'editor_type' => 'markdown',
            'status' => 'published',
            'categories' => [$category->id],
            'tags' => ['release', 'notes'],
        ], $author->id);

        $this->revision = BlogPostRevision::query()->where('post_id', $this->post->id)->latest('id')->firstOrFail();

        $service->create([
            'title' => 'Old post',
            'content' => 'Gone',
            'editor_type' => 'markdown',
            'status' => 'draft',
        ], $author->id)->delete();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themeDirectory);

        parent::tearDown();
    }

    public function test_the_post_list_shows_each_permalink(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        app(BlogPostService::class)->create([
            'title' => 'Draft post',
            'slug' => 'draft-post',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
        ], null);

        $public = route('dixlase-blog::front.show', ['slug' => $this->post->slug]);
        $draft = route('dixlase-blog::front.show', ['slug' => 'draft-post']);

        $this->get(route('dixlase-blog::admin.blog.index'))->assertOk()
            // A visible post's permalink is a link; a draft's is only text.
            ->assertSee('href="'.$public.'"', false)
            ->assertSee($draft, false)
            ->assertDontSee('href="'.$draft.'"', false);
    }

    public function test_the_trash_lists_posts_in_the_post_list_table(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        $this->post->delete();

        $this->get(route('dixlase-blog::admin.blog.trash'))->assertOk()
            ->assertSee('class="admin-table"', false)
            ->assertSee($this->post->title)
            ->assertSee('action="'.route('dixlase-blog::admin.blog.restore', ['id' => $this->post->id]).'"', false)
            ->assertSee('action="'.route('dixlase-blog::admin.blog.forceDelete', ['id' => $this->post->id]).'"', false);
    }

    public function test_every_admin_screen_opens_for_an_admin(): void
    {
        $this->assertScreensOpenFor(MemberRole::ADMIN);
    }

    public function test_every_admin_screen_opens_for_an_editor(): void
    {
        $this->assertScreensOpenFor(MemberRole::EDITOR);
    }

    public function test_the_create_screen_and_preview_frame_open_without_a_post(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        $this->get(route('dixlase-blog::admin.blog.create'))->assertOk();
        $this->get(route('dixlase-blog::admin.blog.previewFrame'))->assertOk();
    }

    private function assertScreensOpenFor(MemberRole $role): void
    {
        $this->actingAs(Member::factory()->create(['role' => $role, 'status' => 1]));

        $opened = [];
        $failures = [];

        foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
            if (! str_starts_with($name, 'dixlase-blog::admin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $response = $this->get(route($name, $this->parametersFor($route->parameterNames())));
            $opened[] = $name;

            if ($response->status() !== 200) {
                $failures[] = sprintf(
                    '%s -> %d %s',
                    $name,
                    $response->status(),
                    $response->exception ? get_class($response->exception).': '.$response->exception->getMessage() : '',
                );
            }
        }

        $this->assertContains('dixlase-blog::admin.blog.index', $opened);
        $this->assertContains('dixlase-blog::admin.blog.revisions.show', $opened);
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * @param  list<string>  $names
     * @return array<string, int>
     */
    private function parametersFor(array $names): array
    {
        $values = [
            'post' => $this->post->id,
            'revisionId' => $this->revision->id,
        ];

        $parameters = [];
        foreach ($names as $name) {
            $this->assertArrayHasKey($name, $values, "No test value for route parameter {{$name}}");
            $parameters[$name] = $values[$name];
        }

        return $parameters;
    }
}
