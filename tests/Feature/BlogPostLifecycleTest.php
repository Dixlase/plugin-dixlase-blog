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

use App\Enums\ContentStatus;
use App\Enums\MemberRole;
use App\Http\Middleware\CheckInstallationReady;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Tests\TestCase;

/**
 * A post's life through the admin screens — create, update, trash,
 * restore, force-delete and revisions — and how slugs are kept apart.
 * Requests go through the admin routes, as the forms send them.
 */
class BlogPostLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app['translator']->addNamespace('dixlase-blog', base_path('plugins/DixlaseBlog/lang'));

        // Core's `plugin` group: session and bindings without the site-wide
        // web middleware, which is not under test.
        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        // CI has no INSTALLED flag, and this global middleware would send
        // every request to the installer.
        $this->withoutMiddleware(CheckInstallationReady::class);

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    public function test_a_post_goes_from_creation_to_permanent_deletion(): void
    {
        $category = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 10]);

        $this->assertRedirectsWithoutErrors($this->post(route('dixlase-blog::admin.blog.store'), $this->postForm([
            'title' => 'Hello world',
            'status' => 'published',
            'categories' => [$category->id],
            'tags' => ['Release'],
        ])));

        $post = BlogPost::query()->sole();
        $this->assertSame('hello-world', $post->slug);
        $this->assertSame(ContentStatus::PUBLISHED, $post->status);
        $this->assertNotNull($post->published_at, 'publishing without a date stamps the current time');
        $this->assertSame([$category->id], $post->categories->pluck('id')->all());
        $this->assertSame(['release'], $post->tags->pluck('slug')->all());
        $this->assertSame(1, $this->revisionCount($post));

        $this->assertRedirectsWithoutErrors($this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $this->postForm([
            'title' => 'Hello again',
            'slug' => 'hello-world',
            'content' => 'Second body',
            'status' => 'published',
            'categories' => [],
            'tags' => ['Release', 'Notes'],
        ])));

        $post->refresh();
        $this->assertSame('Hello again', $post->title);
        $this->assertSame('hello-world', $post->slug, 'a post keeps its own slug on update');
        $this->assertSame([], $post->categories->pluck('id')->all());
        $this->assertEqualsCanonicalizing(['release', 'notes'], $post->tags->pluck('slug')->all());
        $this->assertSame(2, $this->revisionCount($post));

        $this->assertRedirectsWithoutErrors($this->delete(route('dixlase-blog::admin.blog.destroy', ['post' => $post->id])));
        $this->assertSoftDeleted($post);

        $this->assertRedirectsWithoutErrors($this->post(route('dixlase-blog::admin.blog.restore', ['id' => $post->id])));
        $this->assertNotSoftDeleted($post);

        // Permanent deletion works from the trash only.
        $this->delete(route('dixlase-blog::admin.blog.forceDelete', ['id' => $post->id]))->assertNotFound();
        $this->delete(route('dixlase-blog::admin.blog.destroy', ['post' => $post->id]));
        $this->assertRedirectsWithoutErrors($this->delete(route('dixlase-blog::admin.blog.forceDelete', ['id' => $post->id])));

        $this->assertSame(0, BlogPost::withTrashed()->count());
        $this->assertSame(0, BlogPostRevision::query()->count());
        $this->assertSame(0, DB::table('plg_dixlase_blog_post_tag')->count());
        // Categories and tags outlive the post.
        $this->assertSame(1, BlogCategory::query()->count());
        $this->assertSame(2, BlogTag::query()->count());
    }

    public function test_saving_an_unchanged_post_adds_no_revision(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello', 'tags' => ['Release']]));
        $post = BlogPost::query()->sole();

        $form = $this->postForm(['title' => 'Hello', 'slug' => 'hello', 'tags' => ['Release']]);
        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $form);
        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $form);

        $this->assertSame(1, $this->revisionCount($post));
    }

    public function test_a_tag_only_change_is_kept_as_a_revision_and_can_be_restored(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello', 'tags' => ['Release']]));
        $post = BlogPost::query()->sole();
        $first = BlogPostRevision::query()->where('post_id', $post->id)->sole();

        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $this->postForm([
            'title' => 'Hello',
            'slug' => 'hello',
            'tags' => ['Notes'],
        ]));
        $this->assertSame(2, $this->revisionCount($post));

        $this->assertRedirectsWithoutErrors($this->post(route('dixlase-blog::admin.blog.revisions.restore', [
            'post' => $post->id,
            'revisionId' => $first->id,
        ])));

        $this->assertSame(['release'], $post->fresh()->tags->pluck('slug')->all());
        // The state before the restore is the latest revision already, so
        // it is not recorded twice and the restore can still be undone.
        $notes = BlogTag::query()->where('slug', 'notes')->value('id');
        $latest = BlogPostRevision::query()->where('post_id', $post->id)->latest('id')->firstOrFail();
        $this->assertSame([(int) $notes], $latest->snapshot['tag_ids']);
        $this->assertSame(2, $this->revisionCount($post));
    }

    public function test_posts_with_the_same_title_get_numbered_slugs(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->assertRedirectsWithoutErrors($this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello'])));
        }

        $this->assertSame(['hello', 'hello-2', 'hello-3'], BlogPost::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_a_slug_typed_by_the_editor_must_be_free(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello']));
        $other = $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Other']));
        $this->assertRedirectsWithoutErrors($other);
        $otherPost = BlogPost::query()->where('slug', 'other')->sole();

        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Third', 'slug' => 'hello']))
            ->assertSessionHasErrors('slug');
        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $otherPost->id]), $this->postForm(['title' => 'Other', 'slug' => 'hello']))
            ->assertSessionHasErrors('slug');

        $this->assertSame(2, BlogPost::query()->count());
        $this->assertSame('other', $otherPost->fresh()->slug);
    }

    public function test_a_post_restored_from_the_trash_moves_off_a_taken_slug(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello']));
        $old = BlogPost::query()->sole();
        $old->delete();

        // A trashed post does not hold its slug.
        $this->assertRedirectsWithoutErrors($this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello again', 'slug' => 'hello'])));
        $new = BlogPost::query()->sole();
        $this->assertSame('hello', $new->slug);

        $this->post(route('dixlase-blog::admin.blog.restore', ['id' => $old->id]))
            ->assertSessionHas('success', __('dixlase-blog::admin.flash.post_restored_renamed', ['slug' => 'hello-2']));

        $this->assertNotSoftDeleted($old);
        $this->assertSame('hello-2', $old->fresh()->slug);
        $this->assertSame('hello', $new->fresh()->slug);
    }

    public function test_a_post_restored_from_the_trash_keeps_a_free_slug(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello']));
        $post = BlogPost::query()->sole();
        $post->delete();

        $this->post(route('dixlase-blog::admin.blog.restore', ['id' => $post->id]))
            ->assertSessionHas('success', __('dixlase-blog::admin.flash.post_restored'));

        $this->assertSame('hello', $post->fresh()->slug);
    }

    public function test_a_revision_restored_onto_a_taken_slug_gets_a_free_one(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Hello']));
        $post = BlogPost::query()->sole();
        $first = BlogPostRevision::query()->where('post_id', $post->id)->sole();

        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $this->postForm(['title' => 'Hello', 'slug' => 'renamed']));
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Other', 'slug' => 'hello']));

        $this->post(route('dixlase-blog::admin.blog.revisions.restore', ['post' => $post->id, 'revisionId' => $first->id]))
            ->assertSessionHas('success', __('dixlase-blog::admin.flash.revision_restored_renamed', ['slug' => 'hello-2']));

        $this->assertSame('hello-2', $post->fresh()->slug);
        $this->assertSame(1, BlogPost::query()->where('slug', 'hello')->count());
    }

    public function test_a_scheduled_post_needs_a_publish_date(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Later', 'status' => 'scheduled', 'published_at' => '']))
            ->assertSessionHasErrors(['published_at' => __('dixlase-blog::admin.post.scheduled_needs_published_at')]);
        $this->assertSame(0, BlogPost::query()->count());

        $this->assertRedirectsWithoutErrors($this->post(route('dixlase-blog::admin.blog.store'), $this->postForm([
            'title' => 'Later',
            'status' => 'scheduled',
            'published_at' => now()->addDay()->format('Y-m-d\\TH:i'),
        ])));
        $post = BlogPost::query()->sole();
        $this->assertSame(ContentStatus::SCHEDULED, $post->status);
        $this->assertTrue($post->published_at->isFuture());

        $this->put(route('dixlase-blog::admin.blog.update', ['post' => $post->id]), $this->postForm([
            'title' => 'Later',
            'slug' => 'later',
            'status' => 'scheduled',
            'published_at' => '',
        ]))->assertSessionHasErrors('published_at');
        $this->assertNotNull($post->fresh()->published_at);
    }

    public function test_tags_are_shared_by_slug(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'One', 'tags' => ['Release']]));
        $this->post(route('dixlase-blog::admin.blog.store'), $this->postForm(['title' => 'Two', 'tags' => ['release', '', 'Release']]));

        $this->assertSame(1, BlogTag::query()->count());
        $this->assertSame(2, DB::table('plg_dixlase_blog_post_tag')->count());
    }

    public function test_categories_with_the_same_label_get_numbered_slugs(): void
    {
        foreach (range(1, 2) as $ignored) {
            $this->assertRedirectsWithoutErrors($this->post(route('dixlase-blog::admin.blog.categories.store'), ['label' => 'News']));
        }

        $this->assertSame(['news', 'news-2'], BlogCategory::query()->orderBy('id')->pluck('slug')->all());

        $this->post(route('dixlase-blog::admin.blog.categories.store'), ['label' => 'Other', 'slug' => 'news'])
            ->assertSessionHasErrors('slug');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function postForm(array $overrides): array
    {
        return $overrides + [
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
        ];
    }

    private function revisionCount(BlogPost $post): int
    {
        return BlogPostRevision::query()->where('post_id', $post->id)->count();
    }

    /**
     * The admin actions redirect back to an admin screen; show where else a
     * request went, and why, instead of a bare failure.
     */
    private function assertRedirectsWithoutErrors(TestResponse $response): void
    {
        $errors = session('errors')?->getBags() ?? [];
        $messages = array_map(fn ($bag) => $bag->all(), $errors);

        $this->assertTrue(
            $response->isRedirect() && str_contains((string) $response->headers->get('Location'), '/admin-custom/blog') && $messages === [],
            sprintf('status %d, location %s, errors %s', $response->status(), (string) $response->headers->get('Location'), json_encode($messages)),
        );
    }
}
