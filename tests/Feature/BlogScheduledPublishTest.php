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
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\DixlaseBlog\App\Console\Commands\PublishScheduledPosts;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Tests\TestCase;

/**
 * A scheduled post whose publish datetime has passed is marked published,
 * every minute, without counting as an edit.
 */
class BlogScheduledPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => base_path('plugins/DixlaseBlog/database/migrations'),
            '--realpath' => true,
        ]);

        $this->app->make(ConsoleKernel::class)->registerCommand($this->app->make(PublishScheduledPosts::class));
    }

    public function test_only_due_scheduled_posts_are_published(): void
    {
        $due = $this->blogPost('due', ContentStatus::SCHEDULED, now()->subMinute());
        $later = $this->blogPost('later', ContentStatus::SCHEDULED, now()->addHour());
        $undated = $this->blogPost('undated', ContentStatus::SCHEDULED, null);
        $draft = $this->blogPost('draft', ContentStatus::DRAFT, now()->subDay());
        $trashed = $this->blogPost('trashed', ContentStatus::SCHEDULED, now()->subDay());
        $trashed->delete();

        $this->artisan('dls:blog:publish-scheduled')
            ->expectsOutput('Published 1 scheduled post(s).')
            ->assertSuccessful();

        $this->assertSame(ContentStatus::PUBLISHED, $due->fresh()->status);
        $this->assertSame(ContentStatus::SCHEDULED, $later->fresh()->status);
        $this->assertSame(ContentStatus::SCHEDULED, $undated->fresh()->status);
        $this->assertSame(ContentStatus::DRAFT, $draft->fresh()->status);
        $this->assertSame(ContentStatus::SCHEDULED, BlogPost::withTrashed()->find($trashed->id)->status);
    }

    public function test_publishing_is_not_an_edit(): void
    {
        $due = $this->blogPost('due', ContentStatus::SCHEDULED, now()->subMinute());
        $due->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $updatedAt = $due->fresh()->updated_at->toDateTimeString();
        $revisions = BlogPostRevision::query()->count();

        $this->artisan('dls:blog:publish-scheduled')->assertSuccessful();

        $this->assertSame($updatedAt, $due->fresh()->updated_at->toDateTimeString());
        $this->assertSame($revisions, BlogPostRevision::query()->count());
    }

    public function test_the_command_runs_every_minute(): void
    {
        (new DixlaseBlogServiceProvider($this->app))->boot();

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'dls:blog:publish-scheduled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    private function blogPost(string $slug, ContentStatus $status, mixed $publishedAt): BlogPost
    {
        return BlogPost::create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'content' => 'Body',
            'editor_type' => ContentEditorType::MARKDOWN,
            'status' => $status,
            'lang' => 'en',
            'published_at' => $publishedAt,
        ]);
    }
}
