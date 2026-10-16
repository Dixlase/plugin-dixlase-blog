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
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Plugins\DixlaseBlog\Tests\Support\FakeTranslationResolver;
use Tests\TestCase;

/**
 * Comments on the post page: when a post takes them, how a new one
 * starts, replies, the site's own comments and the spam checks.
 */
class BlogCommentsTest extends TestCase
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
        DixlaseBlogServiceProvider::defineCommentRateLimiter();
        app()->setLocale('en');

        // A bare layout in a throwaway directory stands in for the theme.
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

        $this->post = BlogPost::create([
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

    public function test_comments_are_off_until_the_site_turns_them_on(): void
    {
        $this->get('/blog/hello')->assertOk()->assertDontSee('id="comment-form"', false);
        $this->postComment()->assertForbidden();

        $this->enableComments();

        $this->get('/blog/hello')->assertOk()->assertSee('id="comment-form"', false);
    }

    public function test_a_post_can_open_or_close_comments_on_its_own(): void
    {
        $this->post->update(['comment_status' => 'open']);
        $this->get('/blog/hello')->assertSee('id="comment-form"', false);

        $this->enableComments();
        $this->post->update(['comment_status' => 'closed']);
        $this->get('/blog/hello')->assertDontSee('id="comment-form"', false);
        $this->postComment()->assertForbidden();
    }

    public function test_comments_close_the_set_number_of_days_after_publishing(): void
    {
        $this->enableComments(['comments_close_after_days' => 1]);
        $this->post->update(['published_at' => now()->subDays(2)]);

        $this->get('/blog/hello')->assertDontSee('id="comment-form"', false);

        // A post opened on its own is not affected.
        $this->post->update(['comment_status' => 'open']);
        $this->get('/blog/hello')->assertSee('id="comment-form"', false);
    }

    public function test_a_comment_waits_for_approval_by_default(): void
    {
        $this->enableComments();

        $this->postComment()->assertRedirect(url('/blog/hello').'#comment-form');

        $comment = BlogComment::query()->sole();
        $this->assertSame(BlogComment::STATUS_PENDING, $comment->status);
        $this->assertSame('reader@example.com', $comment->author_email);
        $this->assertSame('127.0.0.1', $comment->ip_address);
        $this->assertSame('en', $comment->lang);

        $this->get('/blog/hello')
            ->assertDontSee('First!')
            ->assertSee(__('dixlase-blog::front.comments.pending_notice'));
    }

    public function test_a_comment_is_published_at_once_when_the_blog_says_so(): void
    {
        $this->enableComments(['comment_moderation' => 'immediate']);

        $this->postComment(['body' => "<b>First!</b>\nSecond line"]);
        $comment = BlogComment::query()->sole();
        $this->assertSame(BlogComment::STATUS_PUBLISHED, $comment->status);

        // Plain text: escaped, line breaks kept; the e-mail is never shown.
        $this->get('/blog/hello')->assertOk()
            ->assertSee('&lt;b&gt;First!&lt;/b&gt;<br>'."\n".'Second line', false)
            ->assertSee('id="comment-'.$comment->id.'"', false)
            ->assertDontSee('reader@example.com');
    }

    public function test_a_comment_with_many_links_is_held(): void
    {
        $this->enableComments(['comment_moderation' => 'immediate', 'comment_link_limit' => 1]);

        $this->postComment(['body' => 'See https://a.example and http://b.example']);

        $this->assertSame(BlogComment::STATUS_PENDING, BlogComment::query()->sole()->status);
    }

    public function test_replies_nest_under_their_comment(): void
    {
        $this->enableComments(['comment_moderation' => 'immediate']);
        $this->postComment(['body' => 'Parent comment']);
        $parent = BlogComment::query()->sole();
        $this->postComment(['body' => 'Child reply', 'parent_id' => $parent->id]);
        $child = BlogComment::query()->where('parent_id', $parent->id)->sole();
        $this->postComment(['body' => 'Grandchild reply', 'parent_id' => $child->id]);

        $html = $this->get('/blog/hello')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~id="comment-'.$parent->id.'".*Parent comment.*<ol class="dixlase-blog-comments__replies.*Child reply.*<ol class="dixlase-blog-comments__replies.*Grandchild reply~s',
            $html,
        );
    }

    public function test_the_reply_link_fills_in_the_form(): void
    {
        $this->enableComments(['comment_moderation' => 'immediate']);
        $this->postComment();
        $parent = BlogComment::query()->sole();

        $this->get('/blog/hello?replyto='.$parent->id)->assertOk()
            ->assertSee('name="parent_id" value="'.$parent->id.'"', false)
            ->assertSee(__('dixlase-blog::front.comments.reply_heading', ['name' => 'Reader']));
    }

    public function test_a_reply_to_a_comment_not_shown_is_refused(): void
    {
        $this->enableComments();
        $this->postComment();
        $pending = BlogComment::query()->sole();

        $this->postComment(['parent_id' => $pending->id])->assertSessionHasErrors('body');

        $this->assertSame(1, BlogComment::query()->count());
    }

    public function test_a_visitor_gives_a_name_an_e_mail_address_and_consent(): void
    {
        $this->enableComments();

        $this->from('/blog/hello')
            ->post('/blog/hello/comments', ['body' => 'Hi'])
            ->assertRedirect(url('/blog/hello').'#comment-form')
            ->assertSessionHasErrors(['author_name', 'author_email', 'privacy_agreed']);

        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_a_filled_honeypot_stores_nothing(): void
    {
        $this->enableComments();

        $this->postComment(['website' => 'https://spam.example'])
            ->assertRedirect(url('/blog/hello').'#comment-form')
            ->assertSessionHas('dixlase_blog_comment', BlogComment::STATUS_PENDING);

        $this->assertSame(0, BlogComment::query()->count());
    }

    public function test_an_editor_signed_in_comments_as_the_site(): void
    {
        $this->enableComments();
        $editor = Member::factory()->create(['role' => MemberRole::EDITOR, 'status' => 1, 'display_name' => 'Site editor']);

        $this->actingAs($editor, 'member')
            ->post('/blog/hello/comments', ['body' => 'From the site'])
            ->assertSessionHasNoErrors();

        $comment = BlogComment::query()->sole();
        $this->assertSame($editor->id, $comment->member_id);
        $this->assertSame('Site editor', $comment->author_name);
        $this->assertNull($comment->author_email);
        $this->assertNull($comment->ip_address);
        // Published although new comments wait for approval.
        $this->assertSame(BlogComment::STATUS_PUBLISHED, $comment->status);

        $this->get('/blog/hello')->assertSee(__('dixlase-blog::front.comments.site_badge'));
    }

    public function test_cards_show_the_published_comment_count(): void
    {
        $this->enableComments(['comment_moderation' => 'immediate']);
        $this->postComment();
        $this->postComment();
        BlogComment::query()->first()->update(['status' => BlogComment::STATUS_SPAM]);

        $this->get('/blog')->assertOk()
            ->assertSee(trans_choice('dixlase-blog::front.comments.count', 1, ['count' => 1]));
    }

    public function test_comments_are_rate_limited(): void
    {
        $this->enableComments();

        for ($i = 0; $i < (int) BlogSettings::DEFAULTS['comment_rate_max_attempts']; $i++) {
            $this->postComment()->assertRedirect();
        }

        $this->postComment()->assertStatus(429);
    }

    public function test_the_rate_limit_follows_the_settings(): void
    {
        $this->enableComments(['comment_rate_max_attempts' => 2, 'comment_rate_decay_minutes' => 1]);

        $this->postComment()->assertRedirect();
        $this->postComment()->assertRedirect();
        $this->postComment()->assertStatus(429);

        // The period has passed.
        $this->travel(61)->seconds();
        $this->postComment()->assertRedirect();
    }

    public function test_the_post_s_comment_setting_is_saved_from_the_edit_form(): void
    {
        $this->actingAs(Member::factory()->create(['role' => MemberRole::EDITOR, 'status' => 1]));
        $form = ['title' => 'Draft', 'content' => 'Body', 'editor_type' => 'markdown', 'status' => 'draft'];

        $this->post(route('dixlase-blog::admin.blog.store'), $form + ['comment_status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertSame('closed', BlogPost::query()->where('title', 'Draft')->sole()->comment_status);

        $this->post(route('dixlase-blog::admin.blog.store'), $form + ['comment_status' => 'sometimes'])
            ->assertSessionHasErrors('comment_status');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function enableComments(array $values = []): void
    {
        app(BlogSettings::class)->save(['comments_enabled' => true] + $values);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postComment(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->from('/blog/hello')->post('/blog/hello/comments', $overrides + [
            'author_name' => 'Reader',
            'author_email' => 'reader@example.com',
            'body' => 'First!',
            'privacy_agreed' => '1',
        ]);
    }
}
