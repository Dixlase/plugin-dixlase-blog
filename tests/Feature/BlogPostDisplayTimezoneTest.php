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
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Support\DisplayTimezoneDatetime;
use Tests\TestCase;

/**
 * The publish / close fields are datetime-local: a wall clock with no
 * offset. Dixlase stores and compares in UTC and shows the display
 * timezone, so the fields are read and rendered in that timezone.
 *
 * Read in UTC instead, a post scheduled for 10:00 on a site displaying
 * Asia/Tokyo goes live at 19:00 local — silently, because the form reads
 * back the value it wrote and only the published page disagrees. These
 * tests therefore assert the raw column as well as the rendered field, and
 * check the instant visibility actually flips at.
 */
class BlogPostDisplayTimezoneTest extends TestCase
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

        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        $this->withoutMiddleware(CheckInstallationReady::class);

        // The site stores UTC and displays Tokyo: the pairing the defect
        // showed up on, and a round nine hours, so an off-by-offset bug
        // cannot pass by rounding.
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Asia/Tokyo']);

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_display_timezone_is_the_one_the_site_shows(): void
    {
        $this->assertSame('Asia/Tokyo', DisplayTimezoneDatetime::zone());
    }

    public function test_a_submitted_wall_clock_is_read_in_the_display_timezone(): void
    {
        $this->assertSame('2026-10-26 01:00:00', DisplayTimezoneDatetime::toUtc('2026-10-26T10:00'));
        $this->assertSame('2026-10-26 01:00:00', DisplayTimezoneDatetime::toUtc('2026-10-26 10:00:00'));
    }

    public function test_a_stored_utc_value_is_rendered_as_a_local_wall_clock(): void
    {
        $this->assertSame('2026-10-26T10:00', DisplayTimezoneDatetime::forInput('2026-10-26 01:00:00'));
        $this->assertSame('2026-10-26T10:00', DisplayTimezoneDatetime::forInput(Carbon::parse('2026-10-26 01:00:00', 'UTC')));
    }

    public function test_the_two_directions_round_trip(): void
    {
        foreach (['2026-01-01T00:00', '2026-06-30T23:59', '2026-10-26T10:00'] as $typed) {
            $this->assertSame($typed, DisplayTimezoneDatetime::forInput(DisplayTimezoneDatetime::toUtc($typed)));
        }
    }

    public function test_an_explicit_offset_in_the_submitted_value_wins(): void
    {
        // Already absolute: it must not be shifted again by the display
        // timezone, or a value that arrives with an offset lands wrong.
        $this->assertSame('2026-10-26 01:00:00', DisplayTimezoneDatetime::toUtc('2026-10-26T01:00:00Z'));
        $this->assertSame('2026-10-26 01:00:00', DisplayTimezoneDatetime::toUtc('2026-10-26T10:00:00+09:00'));
    }

    public function test_an_empty_or_unparseable_value_is_null(): void
    {
        foreach (['', '   ', null, 'not a date', 42] as $value) {
            $this->assertNull(DisplayTimezoneDatetime::toUtc($value));
        }
    }

    public function test_a_daylight_saving_zone_uses_the_offset_in_force_on_the_day(): void
    {
        config(['app.display_timezone' => 'America/New_York']);

        // ConfigHelper reads site_settings before config(), so assert the
        // override actually took before reading anything into the offsets
        // below — otherwise a stale setting fails this as an off-by-offset
        // bug that is not there.
        $this->assertSame('America/New_York', DisplayTimezoneDatetime::zone());

        // Same wall clock, six months apart: EST is -05:00, EDT is -04:00.
        // A fixed offset would make these two equal.
        $this->assertSame('2026-01-15 15:00:00', DisplayTimezoneDatetime::toUtc('2026-01-15T10:00'));
        $this->assertSame('2026-07-15 14:00:00', DisplayTimezoneDatetime::toUtc('2026-07-15T10:00'));
    }

    public function test_a_scheduled_post_is_stored_as_the_utc_instant_of_the_typed_local_time(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Scheduled',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'scheduled',
            'published_at' => '2026-10-26T10:00',
        ])->assertSessionHasNoErrors();

        // The raw column, not the model: the cast would hide a wrong zone.
        $this->assertSame('2026-10-26 01:00:00', DB::table('plg_dixlase_blog_posts')->value('published_at'));
    }

    public function test_a_scheduled_post_becomes_visible_at_the_typed_local_time(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Scheduled',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'scheduled',
            'published_at' => '2026-10-26T10:00',
        ])->assertSessionHasNoErrors();

        $id = (int) DB::table('plg_dixlase_blog_posts')->value('id');

        Carbon::setTestNow(Carbon::parse('2026-10-26 09:59:00', 'Asia/Tokyo'));
        $this->assertFalse(BlogPost::query()->currentlyVisible()->whereKey($id)->exists());

        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', 'Asia/Tokyo'));
        $this->assertTrue(BlogPost::query()->currentlyVisible()->whereKey($id)->exists());
    }

    public function test_the_edit_screen_shows_the_local_wall_clock(): void
    {
        $post = BlogPost::create([
            'title' => 'Scheduled',
            'slug' => 'scheduled',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'scheduled',
            'lang' => 'en',
            'published_at' => '2026-10-26 01:00:00',
            'closed_at' => '2026-11-02 01:00:00',
        ]);

        $html = $this->get(route('dixlase-blog::admin.blog.edit', ['post' => $post->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="2026-10-26T10:00"', (string) $html);
        $this->assertStringContainsString('value="2026-11-02T10:00"', (string) $html);
        // The field no longer leaves the member guessing which clock it means.
        $this->assertStringContainsString('Asia/Tokyo', (string) $html);
    }

    public function test_a_redisplayed_form_shows_the_local_wall_clock_not_the_stored_utc(): void
    {
        // What prepareForValidation() merges is UTC, so that is what is
        // flashed on a failed validation. Printed raw it would show the
        // member 01:00 for the 10:00 they typed.
        $html = $this->withSession(['_old_input' => ['published_at' => '2026-10-26 01:00:00']])
            ->get(route('dixlase-blog::admin.blog.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="2026-10-26T10:00"', (string) $html);
    }

    public function test_an_unparseable_datetime_is_reported_rather_than_dropped(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Scheduled',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'scheduled',
            'published_at' => 'not a date',
        ])->assertSessionHasErrors('published_at');

        $this->assertSame(0, DB::table('plg_dixlase_blog_posts')->count());
    }

    public function test_a_close_time_before_the_publish_time_is_still_refused(): void
    {
        // after:published_at compares the normalized values, so the rule
        // keeps working now that both are rewritten before validation.
        $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Scheduled',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'scheduled',
            'published_at' => '2026-10-26T10:00',
            'closed_at' => '2026-10-26T09:00',
        ])->assertSessionHasErrors('closed_at');
    }

    public function test_an_empty_datetime_stays_null(): void
    {
        $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Draft',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
            'published_at' => '',
            'closed_at' => '',
        ])->assertSessionHasNoErrors();

        $row = DB::table('plg_dixlase_blog_posts')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->published_at);
        $this->assertNull($row->closed_at);
    }
}
