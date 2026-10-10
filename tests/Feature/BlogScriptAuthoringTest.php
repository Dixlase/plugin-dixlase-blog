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
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostUpdateRequest;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogContentRenderer;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;
use Tests\TestCase;

/**
 * Only ADMIN and above may write HTML post bodies, and Markdown is rendered
 * with raw HTML escaped -- the same rules as DixlasePages.
 */
class BlogScriptAuthoringTest extends TestCase
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
    }

    public function test_only_admin_and_above_may_author_scripts(): void
    {
        $this->assertFalse(BlogScriptAuthoringPolicy::canAuthorScripts(null));
        $this->assertFalse(BlogScriptAuthoringPolicy::canAuthorScripts($this->member(MemberRole::CONTRIBUTOR)));
        $this->assertFalse(BlogScriptAuthoringPolicy::canAuthorScripts($this->member(MemberRole::EDITOR)));
        $this->assertTrue(BlogScriptAuthoringPolicy::canAuthorScripts($this->member(MemberRole::ADMIN)));
        $this->assertTrue(BlogScriptAuthoringPolicy::canAuthorScripts($this->member(MemberRole::SUPER_ADMIN)));
    }

    public function test_html_is_offered_only_to_admins_in_advanced_mode(): void
    {
        $editor = $this->member(MemberRole::EDITOR);
        $admin = $this->member(MemberRole::ADMIN);

        $this->assertSame(['gui', 'markdown'], BlogScriptAuthoringPolicy::creatableEditorSlugs($admin));

        $this->advancedMode();
        $this->assertSame(['gui', 'markdown'], BlogScriptAuthoringPolicy::creatableEditorSlugs($editor));
        $this->assertSame(['gui', 'markdown', 'html'], BlogScriptAuthoringPolicy::creatableEditorSlugs($admin));
    }

    public function test_an_editor_cannot_create_an_html_post(): void
    {
        $this->advancedMode();
        $this->actingAs($this->member(MemberRole::EDITOR), 'member');

        $validator = $this->validateStore(['editor_type' => 'html']);

        $this->assertArrayHasKey('editor_type', $validator->errors()->toArray());
    }

    public function test_nobody_can_create_a_blade_post(): void
    {
        $this->advancedMode();
        $this->actingAs($this->member(MemberRole::SUPER_ADMIN), 'member');

        $this->assertArrayHasKey('editor_type', $this->validateStore(['editor_type' => 'blade'])->errors()->toArray());
    }

    public function test_an_admin_can_create_an_html_post_in_advanced_mode(): void
    {
        $this->advancedMode();
        $this->actingAs($this->member(MemberRole::ADMIN), 'member');

        $validator = $this->validateStore(['editor_type' => 'html']);

        $this->assertFalse($validator->fails(), (string) json_encode($validator->errors()->toArray()));
    }

    public function test_an_editor_cannot_update_an_html_post(): void
    {
        $this->actingAs($this->member(MemberRole::EDITOR), 'member');

        $this->assertFalse($this->updateRequestFor($this->makePost(ContentEditorType::HTML))->authorize());
        $this->assertTrue($this->updateRequestFor($this->makePost(ContentEditorType::MARKDOWN, 'md'))->authorize());
    }

    public function test_the_editor_type_cannot_be_switched_on_update(): void
    {
        $this->advancedMode();
        $this->actingAs($this->member(MemberRole::ADMIN), 'member');
        $request = $this->updateRequestFor($this->makePost(ContentEditorType::MARKDOWN), ['editor_type' => 'html']);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertArrayHasKey('editor_type', $validator->errors()->toArray());
    }

    public function test_markdown_is_rendered_with_raw_html_escaped_and_unsafe_links_dropped(): void
    {
        $html = app(BlogContentRenderer::class)->render(
            "<script>alert(1)</script>\n\n[link](javascript:alert(1)) **bold**",
            ContentEditorType::MARKDOWN,
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_html_is_rendered_as_written_and_other_types_as_text(): void
    {
        $renderer = app(BlogContentRenderer::class);

        $this->assertSame('<p>Hi</p>', $renderer->render('<p>Hi</p>', ContentEditorType::HTML));
        $this->assertSame('<pre>&lt;b&gt;x&lt;/b&gt;</pre>', $renderer->render('<b>x</b>', ContentEditorType::BLADE));
        $this->assertSame('<pre>&lt;b&gt;x&lt;/b&gt;</pre>', $renderer->render('<b>x</b>', null));
    }

    private function member(MemberRole $role): Member
    {
        return Member::factory()->create(['role' => $role, 'status' => 1]);
    }

    private function advancedMode(): void
    {
        SiteSetting::setValue('admin_mode', '1');
    }

    private function makePost(ContentEditorType $editorType, string $slug = 'post'): BlogPost
    {
        return BlogPost::create([
            'title' => 'Hello',
            'slug' => $slug,
            'content' => 'Body',
            'editor_type' => $editorType,
            'status' => ContentStatus::DRAFT,
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

        // The admin routes pass {post} as an id, not a bound model.
        $route = new Route('PUT', 'blog/{post}', []);
        $route->bind($request);
        $route->setParameter('post', (string) $post->id);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
