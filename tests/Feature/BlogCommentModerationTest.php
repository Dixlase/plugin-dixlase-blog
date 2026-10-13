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
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Mail\BlogCommentNotification;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Services\BlogCommentDashboardProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * The comments screen (approve, unpublish, spam, trash, restore, delete,
 * bulk, reply as the site), the mail to the admin and the dashboard count.
 */
class BlogCommentModerationTest extends TestCase
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

    public function test_the_screen_lists_comments_by_state(): void
    {
        $this->comment(['body' => 'Waiting one']);
        $this->comment(['body' => 'Shown one', 'status' => BlogComment::STATUS_PUBLISHED]);
        $this->actingAsRole(MemberRole::EDITOR);

        $this->get(route('dixlase-blog::admin.blog.comments.index'))->assertOk()
            ->assertSee('Waiting one')
            ->assertDontSee('Shown one')
            ->assertSee('reader@example.com');
        $this->get(route('dixlase-blog::admin.blog.comments.index', ['status' => 'published']))->assertOk()
            ->assertSee('Shown one')
            ->assertDontSee('Waiting one');
    }

    public function test_a_comment_is_approved_unpublished_and_marked_as_spam(): void
    {
        $comment = $this->comment();
        $this->actingAsRole(MemberRole::EDITOR);

        foreach ([BlogComment::STATUS_PUBLISHED, BlogComment::STATUS_PENDING, BlogComment::STATUS_SPAM] as $status) {
            $this->post(route('dixlase-blog::admin.blog.comments.status', ['comment' => $comment->id]), ['status' => $status])
                ->assertSessionHasNoErrors();
            $this->assertSame($status, $comment->fresh()->status);
        }
    }

    public function test_a_comment_is_trashed_restored_and_deleted_by_an_admin(): void
    {
        $comment = $this->comment(['status' => BlogComment::STATUS_PUBLISHED]);
        $reply = $this->comment(['parent_id' => $comment->id]);
        $this->actingAsRole(MemberRole::EDITOR);

        $this->post(route('dixlase-blog::admin.blog.comments.destroy', ['comment' => $comment->id]));
        $this->assertSoftDeleted($comment);
        $this->post(route('dixlase-blog::admin.blog.comments.restore', ['comment' => $comment->id]));
        $this->assertNotSoftDeleted($comment);

        $comment->delete();
        $this->post(route('dixlase-blog::admin.blog.comments.forceDelete', ['comment' => $comment->id]))->assertForbidden();

        $this->actingAsRole(MemberRole::ADMIN);
        $this->post(route('dixlase-blog::admin.blog.comments.forceDelete', ['comment' => $comment->id]));
        $this->assertDatabaseMissing('plg_dixlase_blog_comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('plg_dixlase_blog_comments', ['id' => $reply->id]);
    }

    public function test_bulk_actions_apply_to_the_selected_comments(): void
    {
        $first = $this->comment();
        $second = $this->comment();
        $untouched = $this->comment();
        $this->actingAsRole(MemberRole::EDITOR);

        $this->post(route('dixlase-blog::admin.blog.comments.bulk'), ['action' => 'approve', 'ids' => [$first->id, $second->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(BlogComment::STATUS_PUBLISHED, $first->fresh()->status);
        $this->assertSame(BlogComment::STATUS_PUBLISHED, $second->fresh()->status);
        $this->assertSame(BlogComment::STATUS_PENDING, $untouched->fresh()->status);

        $this->post(route('dixlase-blog::admin.blog.comments.bulk'), ['action' => 'trash', 'ids' => [$first->id]]);
        $this->assertSoftDeleted($first);

        // Deleting for good stays with ADMIN.
        $this->post(route('dixlase-blog::admin.blog.comments.bulk'), ['action' => 'delete', 'ids' => [$first->id]])->assertForbidden();
        $this->post(route('dixlase-blog::admin.blog.comments.bulk'), ['action' => 'approve'])->assertSessionHasErrors('ids');
    }

    public function test_a_reply_from_the_screen_is_the_site_s_and_approves_the_comment(): void
    {
        $comment = $this->comment();
        $editor = $this->actingAsRole(MemberRole::EDITOR);

        $this->post(route('dixlase-blog::admin.blog.comments.reply', ['comment' => $comment->id]), [
            'reply_body' => [$comment->id => 'Thanks for writing'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(BlogComment::STATUS_PUBLISHED, $comment->fresh()->status);
        $reply = BlogComment::query()->where('parent_id', $comment->id)->sole();
        $this->assertSame($editor->id, $reply->member_id);
        $this->assertSame('Thanks for writing', $reply->body);
        $this->assertSame(BlogComment::STATUS_PUBLISHED, $reply->status);
    }

    public function test_the_admin_is_e_mailed_about_a_visitor_comment(): void
    {
        Mail::fake();
        app(BlogSettings::class)->save(['comments_enabled' => true, 'comment_notify_email' => 'editor@example.com']);

        $this->postFromVisitor();

        Mail::assertSent(BlogCommentNotification::class, fn ($mail) => $mail->hasTo('editor@example.com'));
    }

    public function test_without_an_address_the_mail_goes_to_the_system_admin(): void
    {
        Mail::fake();
        SiteSetting::setValue('system_admin_email', 'system@example.com');
        app(BlogSettings::class)->save(['comments_enabled' => true]);

        $this->postFromVisitor();

        Mail::assertSent(BlogCommentNotification::class, fn ($mail) => $mail->hasTo('system@example.com'));
    }

    public function test_no_mail_when_the_notification_is_off_or_the_site_comments(): void
    {
        Mail::fake();
        app(BlogSettings::class)->save(['comments_enabled' => true, 'comment_notify' => false, 'comment_notify_email' => 'editor@example.com']);
        $this->postFromVisitor();

        app(BlogSettings::class)->save(['comment_notify' => true]);
        $this->actingAsRole(MemberRole::EDITOR);
        $this->post('/blog/hello/comments', ['body' => 'From the site']);

        Mail::assertNothingSent();
    }

    public function test_the_mail_shows_the_comment_as_plain_text(): void
    {
        $comment = $this->comment(['body' => '[Click](https://evil.example)']);

        $html = (new BlogCommentNotification($comment, $this->blogPost, 'https://example.com/admin'))->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
    }

    public function test_the_dashboard_counts_the_pending_comments(): void
    {
        $this->assertSame([], app(BlogCommentDashboardProvider::class)->getNotifications());

        $this->comment();
        $this->comment();

        $notifications = app(BlogCommentDashboardProvider::class)->getNotifications();
        $this->assertCount(1, $notifications);
        $this->assertStringContainsString('2', $notifications[0]->message);
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
