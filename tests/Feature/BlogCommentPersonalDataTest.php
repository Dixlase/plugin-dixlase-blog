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

use App\Contracts\PluginIntegration\PrivacyPolicyProviderInterface;
use App\Contracts\TranslationResolver;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Enums\MemberRole;
use App\Http\Middleware\CheckInstallationReady;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\Member;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Console\Commands\PruneCommentIpAddresses;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * Commenters' personal data: IP addresses kept for a set time, erasing
 * one address's data or comments, and the privacy policy link.
 */
class BlogCommentPersonalDataTest extends TestCase
{
    use RefreshDatabase;

    private string $layoutDirectory;

    private BlogPost $blogPost;

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
        DixlaseBlogServiceProvider::defineCommentRateLimiter();
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

        $this->blogPost = BlogPost::create([
            'title' => 'Hello',
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

    public function test_no_ip_address_is_stored_when_the_blog_keeps_none(): void
    {
        app(BlogSettings::class)->save(['comments_enabled' => true, 'comment_ip_retention_days' => 0]);

        $this->postFromVisitor();

        $this->assertNull(BlogComment::query()->sole()->ip_address);
    }

    public function test_the_prune_command_clears_old_ip_addresses(): void
    {
        $this->app->make(ConsoleKernel::class)->registerCommand($this->app->make(PruneCommentIpAddresses::class));
        app(BlogSettings::class)->save(['comment_ip_retention_days' => 7]);
        $old = $this->comment();
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        $recent = $this->comment();

        $this->artisan('dls:blog:prune-comment-ips')->assertSuccessful();

        $this->assertNull($old->fresh()->ip_address);
        $this->assertSame('127.0.0.1', $recent->fresh()->ip_address);
    }

    public function test_an_address_s_comments_are_found_in_any_state_and_case(): void
    {
        $this->comment(['body' => 'Live one']);
        $this->comment(['body' => 'Trashed one'])->delete();
        $this->comment(['body' => 'Someone else', 'author_email' => 'other@example.com']);
        $this->actingAsRole(MemberRole::ADMIN);

        $this->get(route('dixlase-blog::admin.blog.comments.personalData.index', ['email' => 'Reader@Example.com']))->assertOk()
            ->assertSee('Live one')
            ->assertSee('Trashed one')
            ->assertDontSee('Someone else');
    }

    public function test_removing_the_personal_data_keeps_the_comments(): void
    {
        $comment = $this->comment();
        $other = $this->comment(['author_email' => 'other@example.com']);
        $this->actingAsRole(MemberRole::ADMIN);

        $this->post(route('dixlase-blog::admin.blog.comments.personalData.erase'), ['email' => 'reader@example.com', 'mode' => 'anonymise'])
            ->assertSessionHasNoErrors();

        $comment->refresh();
        $this->assertSame('Anonymous', $comment->author_name);
        $this->assertNull($comment->author_email);
        $this->assertNull($comment->ip_address);
        $this->assertSame('A comment', $comment->body);
        $this->assertSame('other@example.com', $other->fresh()->author_email);
    }

    public function test_deleting_removes_the_comments_and_their_replies(): void
    {
        $comment = $this->comment();
        $reply = $this->comment(['parent_id' => $comment->id, 'author_email' => 'other@example.com']);
        $this->actingAsRole(MemberRole::ADMIN);

        $this->post(route('dixlase-blog::admin.blog.comments.personalData.erase'), ['email' => 'reader@example.com', 'mode' => 'delete'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('plg_dixlase_blog_comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('plg_dixlase_blog_comments', ['id' => $reply->id]);
    }

    public function test_the_form_links_the_privacy_policy_set_on_the_blog(): void
    {
        app(BlogSettings::class)->save(['comments_enabled' => true, 'privacy_policy_url' => 'https://example.com/privacy']);

        $this->get('/blog/hello')->assertOk()->assertSee('href="https://example.com/privacy"', false);
    }

    public function test_a_plugin_s_privacy_policy_wins(): void
    {
        app(BlogSettings::class)->save(['comments_enabled' => true, 'privacy_policy_url' => 'https://example.com/privacy']);
        $this->app->instance(PrivacyPolicyProviderInterface::class, new class implements PrivacyPolicyProviderInterface {
            public function getPrivacyPolicyUrl(): ?string
            {
                return 'https://example.com/legal/privacy';
            }

            public function isPrivacyPolicyEnabled(): bool
            {
                return true;
            }

            public function getPrivacyPolicyLabel(): string
            {
                return 'Privacy policy';
            }
        });

        $this->get('/blog/hello')->assertOk()
            ->assertSee('href="https://example.com/legal/privacy"', false)
            ->assertDontSee('href="https://example.com/privacy"', false);
    }

    public function test_trashed_comments_are_offered_to_core_s_cleanup(): void
    {
        $config = require base_path('plugins/DixlaseBlog/config/admin/database-cleanup.php');

        $this->assertSame('plg_dixlase_blog_comments', $config['trashed_comments']['table']);
        $this->assertSame('deleted_at', $config['trashed_comments']['date_column']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function comment(array $attributes = []): BlogComment
    {
        return BlogComment::create($attributes + [
            'post_id' => $this->blogPost->id,
            'author_name' => 'Reader',
            'author_email' => 'reader@example.com',
            'body' => 'A comment',
            'status' => BlogComment::STATUS_PENDING,
            'ip_address' => '127.0.0.1',
        ]);
    }

    private function postFromVisitor(): void
    {
        $this->post('/blog/hello/comments', [
            'author_name' => 'Reader',
            'author_email' => 'reader@example.com',
            'body' => 'Hello there',
            'privacy_agreed' => '1',
        ])->assertSessionHasNoErrors();
    }

    private function actingAsRole(MemberRole $role): Member
    {
        $member = Member::factory()->create(['role' => $role, 'status' => 1]);
        $this->actingAs($member);

        return $member;
    }
}
