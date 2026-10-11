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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Tests\TestCase;

/**
 * An admin form submits "" for a field left empty. Those must be stored as
 * NULL: MySQL refuses "" in an integer or datetime column. SQLite accepts
 * it, so these tests read the raw column and check for NULL rather than
 * relying on the insert to fail.
 */
class BlogAdminBlankInputTest extends TestCase
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
        // web middleware (safe mode, maintenance), which is not under test.
        Route::middleware('plugin')->prefix('admin-custom')->group(base_path('plugins/DixlaseBlog/routes/admin.php'));
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        // CI has no INSTALLED flag, and this global middleware would send
        // every request to the installer.
        $this->withoutMiddleware(CheckInstallationReady::class);

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    public function test_a_post_saved_with_empty_optional_fields_stores_null(): void
    {
        $response = $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Hello',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
            'featured_media_id' => '',
            'published_at' => '',
            'closed_at' => '',
        ]);
        $this->assertSaved($response);

        $row = DB::table('plg_dixlase_blog_posts')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->featured_media_id);
        $this->assertNull($row->published_at);
        $this->assertNull($row->closed_at);
    }

    public function test_a_media_picker_zero_means_no_featured_image(): void
    {
        $response = $this->post(route('dixlase-blog::admin.blog.store'), [
            'title' => 'Hello',
            'content' => 'Body',
            'editor_type' => 'markdown',
            'status' => 'draft',
            'featured_media_id' => '0',
        ]);
        $this->assertSaved($response);

        $this->assertSame(1, DB::table('plg_dixlase_blog_posts')->count());
        $this->assertNull(DB::table('plg_dixlase_blog_posts')->value('featured_media_id'));
    }

    public function test_a_top_level_category_is_created_from_an_empty_parent(): void
    {
        $response = $this->post(route('dixlase-blog::admin.blog.categories.store'), [
            'label' => 'News',
            'slug' => '',
            'parent_id' => '',
            'display_order' => '',
        ]);
        $this->assertSaved($response);

        $row = DB::table('plg_dixlase_blog_categories')->where('label', 'News')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->parent_id);
        $this->assertNotSame('', $row->slug);
    }

    public function test_a_child_category_is_created_from_a_text_parent_id(): void
    {
        $parent = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 10]);

        $response = $this->post(route('dixlase-blog::admin.blog.categories.store'), [
            'label' => 'Releases',
            'parent_id' => (string) $parent->id,
        ]);
        $this->assertSaved($response);

        $child = BlogCategory::query()->where('label', 'Releases')->first();
        $this->assertNotNull($child);
        $this->assertSame($parent->id, (int) $child->parent_id);
        $this->assertSame(10, (int) $child->display_order);
    }

    /**
     * The store actions redirect back to the list or the edit screen; show
     * where else a request went, and why, instead of a bare failure.
     */
    private function assertSaved(\Illuminate\Testing\TestResponse $response): void
    {
        $errors = session('errors')?->getBags() ?? [];
        $messages = array_map(fn ($bag) => $bag->all(), $errors);

        $this->assertTrue(
            $response->isRedirect() && str_contains((string) $response->headers->get('Location'), '/admin-custom/blog') && $messages === [],
            sprintf('status %d, location %s, errors %s', $response->status(), (string) $response->headers->get('Location'), json_encode($messages)),
        );
    }
}
