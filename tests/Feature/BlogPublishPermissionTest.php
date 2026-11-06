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

use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Enums\MemberRole;
use App\Models\Member;
use App\Models\RolePermissionOverride;
use App\Services\PermissionRegistry;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Plugins\DixlaseBlog\App\Console\Commands\ImportPost;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostUpdateRequest;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogPublishPolicy;
use Tests\TestCase;

/**
 * Publishing a post is its own permission, `blog.publish` (#219). Without it
 * a member saves posts only as drafts and cannot change a live one.
 */
class BlogPublishPermissionTest extends TestCase
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
        PermissionRegistry::clearCache();
    }

    public function test_editors_may_publish_by_default(): void
    {
        $effective = PermissionRegistry::getPluginEffective('DixlaseBlog', BlogPublishPolicy::MENU_KEY);
        $this->assertSame(MemberRole::EDITOR->value, $effective['access_roles']);

        $editor = $this->member(MemberRole::EDITOR);
        $this->assertTrue(BlogPublishPolicy::canPublish($editor));
        $this->assertSame(['draft', 'published', 'scheduled'], BlogPublishPolicy::allowedStatusSlugs($editor));

        $this->actingAs($editor, 'member');
        $this->assertArrayNotHasKey('status', $this->validateStore(['status' => 'published'])->errors()->toArray());
        $this->assertTrue($this->updateRequestFor($this->makePost(ContentStatus::PUBLISHED))->authorize());
    }

    public function test_nobody_publishes_without_a_role(): void
    {
        $this->assertFalse(BlogPublishPolicy::canPublish(null));
        $this->assertFalse(BlogPublishPolicy::canPublish($this->member(MemberRole::CONTRIBUTOR)));
    }

    public function test_without_the_permission_a_post_is_saved_only_as_a_draft(): void
    {
        $this->publishingForAdminsOnly();
        $this->actingAs($this->member(MemberRole::EDITOR), 'member');

        $this->assertSame(['draft'], BlogPublishPolicy::allowedStatusSlugs($this->member(MemberRole::EDITOR)));
        $this->assertArrayNotHasKey('status', $this->validateStore(['status' => 'draft'])->errors()->toArray());

        foreach (['published', 'scheduled'] as $status) {
            $errors = $this->validateStore(['status' => $status, 'published_at' => '2030-01-01 10:00'])->errors()->toArray();
            $this->assertSame([__('dixlase-blog::admin.post.publish_not_allowed')], $errors['status'] ?? null, $status);
        }

        $this->assertTrue(BlogPublishPolicy::canPublish($this->member(MemberRole::ADMIN)));
        $this->assertTrue(BlogPublishPolicy::canPublish($this->member(MemberRole::SUPER_ADMIN)));
    }

    public function test_without_the_permission_a_live_post_cannot_be_changed(): void
    {
        $this->publishingForAdminsOnly();
        $editor = $this->member(MemberRole::EDITOR);
        $this->actingAs($editor, 'member');

        $draft = $this->makePost(ContentStatus::DRAFT, 'draft');
        $live = $this->makePost(ContentStatus::PUBLISHED, 'live');
        $scheduled = $this->makePost(ContentStatus::SCHEDULED, 'later');

        $this->assertTrue($this->updateRequestFor($draft)->authorize());
        $this->assertFalse($this->updateRequestFor($live)->authorize());
        $this->assertFalse($this->updateRequestFor($scheduled)->authorize());

        $this->assertTrue(BlogPublishPolicy::canChange($editor, $draft));
        $this->assertFalse(BlogPublishPolicy::canChange($editor, $live));
        $this->assertTrue(BlogPublishPolicy::canChange($this->member(MemberRole::ADMIN), $live));
    }

    public function test_the_import_follows_the_same_rules(): void
    {
        $this->publishingForAdminsOnly();
        Member::factory()->create(['role' => MemberRole::EDITOR, 'status' => 1, 'email' => 'writer@example.com']);
        $this->app->make(ConsoleKernel::class)->registerCommand($this->app->make(ImportPost::class));

        $dir = storage_path('framework/testing/blog-publish-'.uniqid());
        File::ensureDirectoryExists($dir);

        try {
            File::put($dir.'/live.md', "---\ntitle: Live\nslug: new-live\nstatus: published\n---\n\nBody");
            $this->import($dir.'/live.md')
                ->expectsOutputToContain(__('dixlase-blog::admin.post.publish_not_allowed'))
                ->assertFailed();
            $this->assertNull(BlogPost::query()->where('slug', 'new-live')->first());

            File::put($dir.'/draft.md', "---\ntitle: Draft\nslug: new-draft\n---\n\nBody");
            $this->import($dir.'/draft.md')->assertSuccessful();
            $this->assertSame(ContentStatus::DRAFT, BlogPost::query()->where('slug', 'new-draft')->firstOrFail()->status);

            $this->makePost(ContentStatus::PUBLISHED, 'already-live', ContentEditorType::MARKDOWN);
            File::put($dir.'/update.md', "---\ntitle: Changed\nslug: already-live\nstatus: draft\n---\n\nBody");
            $this->import($dir.'/update.md')
                ->expectsOutputToContain(__('dixlase-blog::import.live_post', ['slug' => 'already-live']))
                ->assertFailed();
            $this->assertSame('Hello', BlogPost::query()->where('slug', 'already-live')->firstOrFail()->title);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function publishingForAdminsOnly(): void
    {
        RolePermissionOverride::create([
            'source_type' => RolePermissionOverride::SOURCE_PLUGIN,
            'source_id' => 'DixlaseBlog',
            'menu_key' => BlogPublishPolicy::MENU_KEY,
            'access_roles' => MemberRole::ADMIN->value,
            'view_roles' => MemberRole::ADMIN->value,
        ]);
        PermissionRegistry::clearCache();
    }

    private function import(string $file): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('dls:blog:post:import', ['file' => $file, '--as' => 'writer@example.com', '--allow-non-local' => true]);
    }

    private function member(MemberRole $role): Member
    {
        return Member::factory()->create(['role' => $role, 'status' => 1]);
    }

    private function makePost(ContentStatus $status, string $slug = 'post', ContentEditorType $editorType = ContentEditorType::MARKDOWN): BlogPost
    {
        return BlogPost::create([
            'title' => 'Hello',
            'slug' => $slug,
            'content' => 'Body',
            'editor_type' => $editorType,
            'status' => $status,
            'published_at' => $status === ContentStatus::DRAFT ? null : now()->addDay(),
            'lang' => 'en',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateStore(array $data): \Illuminate\Validation\Validator
    {
        $request = BlogPostStoreRequest::create('/blog', 'POST', $data + [
            'title' => 'Hello',
            'content' => 'Body',
            'status' => 'draft',
            'editor_type' => 'markdown',
        ]);
        $request->setContainer($this->app);

        return Validator::make($request->all(), $request->rules(), $request->messages());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateRequestFor(BlogPost $post, array $data = []): BlogPostUpdateRequest
    {
        $request = BlogPostUpdateRequest::create('/blog/'.$post->id, 'PUT', $data + [
            'title' => 'Hello',
            'content' => 'Body',
            'status' => 'draft',
            'editor_type' => $post->editor_type->slug(),
        ]);
        $request->setContainer($this->app);

        $route = new Route('PUT', 'blog/{post}', []);
        $route->bind($request);
        $route->setParameter('post', (string) $post->id);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
