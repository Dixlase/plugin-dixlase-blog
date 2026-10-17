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
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogCustomAssetController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFeedController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostUpdateRequest;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * An HTML post's own CSS and JavaScript: kept only for HTML posts, served
 * as files next to a visible post, and in the editors' previews the CSS
 * only.
 */
class BlogPostCustomAssetsTest extends TestCase
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

        // A bare layout in a throwaway directory stands in for the theme.
        $this->layoutDirectory = storage_path('framework/testing/blog-layout-'.uniqid());
        File::ensureDirectoryExists($this->layoutDirectory.'/layouts');
        File::put(
            $this->layoutDirectory.'/layouts/app.blade.php',
            "<html><head>@stack('styles')</head><body>@yield('content')@stack('scripts')</body></html>",
        );
        $this->app['view']->replaceNamespace('themes', [$this->layoutDirectory]);

        $this->withoutMiddleware([
            CheckInstallationReady::class,
            CheckMaintenanceMode::class,
        ]);

        Route::middleware('web')->name('dixlase-blog::front.')->group(function () {
            Route::get('/blog', [DixlaseBlogFrontController::class, 'index'])->name('index');
            Route::get('/blog/feed.xml', [DixlaseBlogFeedController::class, 'feed'])->name('feed');
            Route::get('/blog/category/{slug}', [DixlaseBlogFrontController::class, 'category'])->name('category');
            Route::get('/blog/tag/{slug}', [DixlaseBlogFrontController::class, 'tag'])->name('tag');
            Route::get('/blog/{slug}/custom-style.css', [DixlaseBlogCustomAssetController::class, 'style'])->name('custom-style');
            Route::get('/blog/{slug}/custom-script.js', [DixlaseBlogCustomAssetController::class, 'script'])->name('custom-script');
            Route::get('/blog/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('show');
        });
        // Under the admin URL, as in routes/admin.php.
        Route::middleware('web')->prefix('admin-custom')->post('/blog/preview-page/{post?}', [DixlaseBlogFrontController::class, 'previewForm'])
            ->whereNumber('post')
            ->name('dixlase-blog::admin.blog.previewForm');
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->app->instance(TranslationResolver::class, new FakeTranslationResolver());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutDirectory);

        parent::tearDown();
    }

    public function test_css_and_js_are_dropped_for_a_post_that_is_not_html(): void
    {
        $assets = ['custom_css' => 'body{}', 'custom_js' => 'alert(1)'];

        $store = $this->prepared(BlogPostStoreRequest::create('/blog', 'POST', $assets + ['editor_type' => 'markdown']));
        $this->assertNull($store->input('custom_css'));
        $this->assertNull($store->input('custom_js'));

        $html = $this->prepared(BlogPostStoreRequest::create('/blog', 'POST', $assets + ['editor_type' => 'html']));
        $this->assertSame('body{}', $html->input('custom_css'));

        $update = $this->prepared($this->updateRequestFor($this->makePost(ContentEditorType::MARKDOWN), $assets));
        $this->assertNull($update->input('custom_css'));
        $this->assertNull($update->input('custom_js'));

        $update = $this->prepared($this->updateRequestFor($this->makePost(ContentEditorType::HTML, 'html'), $assets));
        $this->assertSame('alert(1)', $update->input('custom_js'));
    }

    public function test_a_form_without_the_fields_keeps_the_stored_ones(): void
    {
        $post = $this->makePost(ContentEditorType::HTML, 'post', ['custom_css' => 'h1{}', 'custom_js' => 'run()']);
        $service = app(BlogPostService::class);

        // Simple mode leaves the tabs out of the form.
        $service->update($post, ['title' => 'Hello', 'content' => 'Body', 'status' => 'draft'], null);
        $this->assertSame('h1{}', $post->fresh()->custom_css);
        $this->assertSame('run()', $post->fresh()->custom_js);

        $service->update($post->fresh(), ['title' => 'Hello', 'content' => 'Body', 'custom_css' => 'p{}', 'custom_js' => null], null);
        $this->assertSame('p{}', $post->fresh()->custom_css);
        $this->assertNull($post->fresh()->custom_js);
    }

    public function test_they_are_kept_in_revisions(): void
    {
        $this->assertContains('custom_css', (new BlogPost())->revisionableFields());
        $this->assertContains('custom_js', (new BlogPost())->revisionableFields());
    }

    public function test_a_visible_html_post_serves_and_links_its_files(): void
    {
        $this->makePost(ContentEditorType::HTML, 'styled', [
            'status' => ContentStatus::PUBLISHED,
            'published_at' => now()->subDay(),
            'custom_css' => 'h1{color:red}',
            'custom_js' => 'console.log(1)',
        ]);

        $this->get('/blog/styled')
            ->assertOk()
            ->assertSee('/blog/styled/custom-style.css?v=', false)
            ->assertSee('/blog/styled/custom-script.js?v=', false);

        $css = $this->get('/blog/styled/custom-style.css')->assertOk();
        $this->assertSame('h1{color:red}', $css->getContent());
        $this->assertStringStartsWith('text/css', (string) $css->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $css->headers->get('Cache-Control'));

        $js = $this->get('/blog/styled/custom-script.js')->assertOk();
        $this->assertSame('console.log(1)', $js->getContent());
        $this->assertStringStartsWith('application/javascript', (string) $js->headers->get('Content-Type'));
    }

    public function test_no_files_for_a_draft_a_post_that_is_not_html_or_an_empty_field(): void
    {
        $this->makePost(ContentEditorType::HTML, 'draft', ['custom_css' => 'h1{}', 'custom_js' => 'x()']);
        $this->makePost(ContentEditorType::MARKDOWN, 'markdown', [
            'status' => ContentStatus::PUBLISHED,
            'published_at' => now()->subDay(),
            'custom_css' => 'h1{}',
        ]);
        $this->makePost(ContentEditorType::HTML, 'plain', [
            'status' => ContentStatus::PUBLISHED,
            'published_at' => now()->subDay(),
            'custom_css' => '  ',
        ]);

        $this->get('/blog/draft/custom-style.css')->assertNotFound();
        $this->get('/blog/draft/custom-script.js')->assertNotFound();
        $this->get('/blog/markdown/custom-style.css')->assertNotFound();
        $this->get('/blog/plain/custom-style.css')->assertNotFound();
        $this->get('/blog/missing/custom-style.css')->assertNotFound();

        $this->get('/blog/markdown')->assertOk()->assertDontSee('custom-style.css', false);
        $this->get('/blog/plain')->assertOk()->assertDontSee('custom-style.css', false);
    }

    public function test_the_form_preview_shows_the_css_being_typed_and_never_the_script(): void
    {
        $post = $this->makePost(ContentEditorType::HTML, 'post', ['custom_css' => 'h1{}', 'custom_js' => 'stored()']);
        $form = [
            'title' => 'Hello',
            'content' => '<p>Body</p>',
            'editor_type' => 'html',
            'status' => 'draft',
            'custom_css' => 'h2{color:blue}</style><script>x()</script>',
            'custom_js' => 'typed()',
        ];

        $this->actingAs($this->member(MemberRole::ADMIN), 'member');
        $this->post('/admin-custom/blog/preview-page/'.$post->id, $form)
            ->assertOk()
            ->assertSee('h2{color:blue}<\/style><script>x()</script></style>', false)
            ->assertDontSee('custom-style.css', false)
            ->assertDontSee('custom-script.js', false)
            ->assertDontSee('typed()', false)
            ->assertDontSee('stored()', false);

        // A member who may not write it sees the stored CSS, not the posted.
        $this->actingAs($this->member(MemberRole::EDITOR), 'member');
        $this->post('/admin-custom/blog/preview-page/'.$post->id, $form)
            ->assertOk()
            ->assertSee('<style', false)
            ->assertSee('h1{}', false)
            ->assertDontSee('h2{color:blue}', false);
    }

    private function member(MemberRole $role): Member
    {
        return Member::factory()->create(['role' => $role, 'status' => 1]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePost(ContentEditorType $editorType, string $slug = 'post', array $attributes = []): BlogPost
    {
        return BlogPost::create($attributes + [
            'title' => 'Hello',
            'slug' => $slug,
            'content' => 'Body',
            'editor_type' => $editorType,
            'status' => ContentStatus::DRAFT,
            'lang' => 'en',
        ]);
    }

    /**
     * Runs the request's prepareForValidation(), as Laravel does before
     * validating.
     *
     * @template T of \Illuminate\Foundation\Http\FormRequest
     *
     * @param  T  $request
     * @return T
     */
    private function prepared($request)
    {
        $request->setContainer($this->app);
        (new \ReflectionMethod($request, 'prepareForValidation'))->invoke($request);

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateRequestFor(BlogPost $post, array $data): BlogPostUpdateRequest
    {
        $request = BlogPostUpdateRequest::create('/blog/'.$post->id, 'PUT', $data + [
            'editor_type' => $post->editor_type->slug(),
        ]);

        // The admin routes pass {post} as an id, not a bound model.
        $route = new RoutingRoute('PUT', 'blog/{post}', []);
        $route->bind($request);
        $route->setParameter('post', (string) $post->id);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
