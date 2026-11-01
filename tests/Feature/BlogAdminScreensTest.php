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
use App\Contracts\Site\SiteContextInterface;
use App\Models\Media;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogComment;
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
        // The post preview (blog.previewPage) renders the front template.
        File::put($this->themeDirectory.'/layouts/app.blade.php', '<html><title>@yield(\'title\')</title><body>@yield(\'content\')</body></html>');
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

    public function test_the_editor_links_to_the_post_or_its_preview(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        $draft = app(BlogPostService::class)->create([
            'title' => 'Draft post',
            'slug' => 'draft-post',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
        ], null);

        // A visible post links to its own page.
        $public = route('dixlase-blog::front.show', ['slug' => $this->post->slug]);
        $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $this->post->id]))->assertOk()
            ->assertSee('href="'.$public.'" target="_blank"', false)
            ->assertDontSee(__('dixlase-blog::admin.post.url_not_public'));

        // A draft shows its address but opens the preview.
        $address = route('dixlase-blog::front.show', ['slug' => 'draft-post']);
        $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $draft->id]))->assertOk()
            ->assertSee('href="'.route('dixlase-blog::admin.blog.previewPage', ['post' => $draft->id]).'" target="_blank"', false)
            ->assertSee('>'.$address.'</a>', false)
            ->assertDontSee('href="'.$address.'"', false)
            ->assertSee(__('dixlase-blog::admin.post.url_not_public'));

        // A new post has no address yet.
        $this->get(route('dixlase-blog::admin.blog.create'))->assertOk()
            ->assertDontSee(__('dixlase-blog::admin.post.url_not_public'));
    }

    public function test_the_post_sidebar_is_grouped_into_collapsible_sections(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        $response = $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $this->post->id]))->assertOk();

        // The sections, in order; the preview button stays outside them.
        $response->assertSeeInOrder([
            __('dixlase-blog::admin.post_form.preview_button'),
            __('dixlase-blog::admin.post_form.meta_section'),
            __('dixlase-blog::admin.post_form.classification_section'),
            __('dixlase-blog::admin.post_form.media_section'),
            __('dixlase-blog::admin.post_form.post_info_section'),
            __('dixlase-blog::admin.post_form.publish_section'),
        ]);

        // Title and publish settings start open, the rest closed.
        $html = $response->getContent();
        foreach (['title' => '1', 'classification' => '0', 'featured' => '0', 'info' => '0', 'publish' => '1'] as $key => $open) {
            $this->assertMatchesRegularExpression(
                '/data-open="'.$open.'"\s+data-force-open="0"\s+data-remember-key="dixlase-blog\.post\.'.$key.'"/',
                $html,
            );
        }

        // A closed section reads its state from the summary.
        $response->assertSee(__('dixlase-blog::admin.post_form.summary_classification', ['categories' => 1, 'tags' => 2]));
        $response->assertSee(__('dixlase-blog::admin.post_form.revision_link'));

        // A new post has no post info to show.
        $this->get(route('dixlase-blog::admin.blog.create'))->assertOk()
            ->assertDontSee('dixlase-blog.post.info', false);
    }

    public function test_a_section_with_an_error_opens(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        // The error as ShareErrorsFromSession would share it after a
        // refused save; the middleware itself would replace it with the
        // test session's empty bag.
        $this->withoutMiddleware(ShareErrorsFromSession::class);
        view()->share('errors', (new ViewErrorBag())->put('default', new MessageBag(['tags.0' => ['Too long.']])));

        $html = $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $this->post->id]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-open="1"\s+data-force-open="1"\s+data-remember-key="dixlase-blog\.post\.classification"/', $html);
        $this->assertMatchesRegularExpression('/data-open="0"\s+data-force-open="0"\s+data-remember-key="dixlase-blog\.post\.featured"/', $html);
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

    public function test_the_post_list_counts_published_and_pending_comments(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        $statuses = [
            BlogComment::STATUS_PUBLISHED, BlogComment::STATUS_PUBLISHED, BlogComment::STATUS_PUBLISHED,
            BlogComment::STATUS_PENDING, BlogComment::STATUS_PENDING,
            BlogComment::STATUS_SPAM,
        ];
        foreach ($statuses as $status) {
            BlogComment::create([
                'post_id' => $this->post->id,
                'author_name' => 'Reader',
                'author_email' => 'reader@example.com',
                'body' => 'A comment',
                'status' => $status,
            ]);
        }
        // A trashed comment is not counted.
        BlogComment::query()->where('status', BlogComment::STATUS_PUBLISHED)->first()->delete();

        $this->get(route('dixlase-blog::admin.blog.index'))->assertOk()
            ->assertSee(__('dixlase-blog::admin.post_index.comments_published', ['count' => 2]))
            ->assertSee(__('dixlase-blog::admin.post_index.comments_pending', ['count' => 2]))
            ->assertSee('href="'.route('dixlase-blog::admin.blog.comments.index', ['status' => BlogComment::STATUS_PENDING]).'"', false);
    }

    public function test_the_category_rows_offer_a_child_and_moving_up(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        $releases = BlogCategory::query()->where('slug', 'releases')->firstOrFail();

        $this->get(route('dixlase-blog::admin.blog.categories.index'))->assertOk()
            ->assertSee('action="'.route('dixlase-blog::admin.blog.categories.outdent', ['category' => $releases->id]).'"', false)
            ->assertSee('name="parent_id" value="'.$releases->id.'"', false)
            ->assertDontSee('<select id="create-parent_id"', false);
    }

    public function test_the_category_rows_offer_moves_without_dragging(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        $releases = BlogCategory::query()->where('slug', 'releases')->firstOrFail();

        $this->get(route('dixlase-blog::admin.blog.categories.index'))->assertOk()
            ->assertSee('@click="moveEarlier('.$releases->id.')"', false)
            ->assertSee('@click="moveLater('.$releases->id.')"', false)
            ->assertSee('@click="moveIntoAbove('.$releases->id.')"', false)
            ->assertSee('aria-label="'.__('dixlase-blog::admin.category.move_into_above').'"', false)
            ->assertSee('role="status" aria-live="polite"', false);

        $script = (string) file_get_contents(base_path('plugins/DixlaseBlog/resources/src/js/blog-category-reorder.js'));
        foreach (['moveEarlier(id)', 'moveLater(id)', 'moveIntoAbove(id)'] as $method) {
            $this->assertStringContainsString($method, $script);
        }
        // From an @click, Alpine's $el is the pressed button, so the methods
        // must reach the tree through the element kept in init().
        $this->assertSame(1, substr_count($script, 'this.$el'));
        $this->assertStringContainsString('this.root = this.$el;', $script);
    }

    public function test_drag_and_drop_finds_the_root_list_to_save(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        // The root list sits inside a wrapper; the reorder script finds it by
        // this marker, not as a direct child of the component element.
        $this->get(route('dixlase-blog::admin.blog.categories.index'))->assertOk()
            ->assertSee('data-parent-id="" data-tree-root', false);

        $script = (string) file_get_contents(base_path('plugins/DixlaseBlog/resources/src/js/blog-category-reorder.js'));
        $this->assertStringContainsString("querySelector('[data-tree-root]')", $script);
        $this->assertStringNotContainsString('this.$el.querySelector(\':scope > .category-tree\')', $script);
        // Core's JSON errors are { error: { code, message } }.
        $this->assertStringContainsString('data.error.message', $script);
    }

    public function test_the_live_preview_shows_the_featured_image(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
        // Media rows belong to the current site.
        $siteId = rescue(fn () => app(SiteContextInterface::class)->currentSiteId(), 1, false);
        $media = Media::query()->create([
            'site_id' => $siteId,
            'name' => 'cover.png',
            'alt_text' => 'Cover',
            'path' => '2026/10/cover.png',
            'type' => 'image/png',
        ]);
        $this->post->update(['featured_media_id' => $media->id]);

        $this->get(route('dixlase-blog::admin.blog.previewFrame', ['post' => $this->post->id]))->assertOk()
            ->assertSee('src="'.asset('storage/media/2026/10/cover.png').'"', false)
            ->assertSee('alt="Cover"', false)
            ->assertSee('updateFeatured', false);
    }

    public function test_the_live_preview_hides_the_featured_image_without_one(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        $html = $this->get(route('dixlase-blog::admin.blog.previewFrame'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<figure id="preview-featured"[^>]*\bhidden\b/', $html);
    }

    public function test_the_live_preview_starts_below_the_fixed_header(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        $html = $this->get(route('dixlase-blog::admin.blog.previewFrame'))->assertOk()->getContent();

        // The theme's preview header is fixed (3.5rem); py-8 left the title under it.
        $this->assertMatchesRegularExpression('/<article class="dixlase-blog-preview[^"]*\bpt-28\b/', $html);
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

    public function test_the_post_form_uses_the_shared_preview_and_marks_required_fields(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));

        $response = $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $this->post->id]))->assertOk();

        // The main column may shrink beside the right sidebar.
        $response->assertSee('#admin-main-content { min-width: 0; }', false);
        // The shared editor runs the preview through the blog's frame and
        // renderer; the side-by-side Markdown view stays off.
        $response->assertSee('&quot;preview&quot;:true', false);
        $response->assertSee('&quot;previewRender&quot;:&quot;server&quot;', false);
        $response->assertSee('x-data="blogPreviewTitle"', false);
        // Title and status carry the required badge.
        foreach (['title', 'status'] as $field) {
            $this->assertSame(1, preg_match('~<label for="'.$field.'"[^>]*>(.*?)</label>~s', $response->getContent(), $label));
            $this->assertStringContainsString(__('common.required'), $label[1], "{$field} is not marked required");
        }
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
            // An old address that only redirects (BlogSettingsTest covers it).
            if ($name === 'dixlase-blog::admin.blog.settings.edit') {
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
        $this->assertContains('dixlase-blog::admin.blog.settings.comments', $opened);
        $this->assertContains('dixlase-blog::admin.blog.settings.storage', $opened);
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
