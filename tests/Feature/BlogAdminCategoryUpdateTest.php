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
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Tests\TestCase;

/**
 * Saving the category edit form. The form sends every field back, the
 * current slug and parent included, so the category being edited must
 * not collide with itself.
 */
class BlogAdminCategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    private BlogCategory $news;

    private BlogCategory $events;

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

        $this->news = BlogCategory::create(['label' => 'News', 'slug' => 'news', 'display_order' => 10]);
        $this->events = BlogCategory::create(['label' => 'Events', 'slug' => 'events', 'display_order' => 20]);
    }

    public function test_a_category_is_renamed_keeping_its_slug(): void
    {
        $this->update($this->news, ['label' => 'Announcements', 'slug' => 'news', 'parent_id' => ''])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dixlase-blog::admin.blog.categories.index'));

        $this->news->refresh();
        $this->assertSame('Announcements', $this->news->label);
        $this->assertSame('news', $this->news->slug);
    }

    public function test_a_category_takes_a_free_slug(): void
    {
        $this->update($this->news, ['label' => 'News', 'slug' => 'updates'])->assertSessionHasNoErrors();

        $this->assertSame('updates', $this->news->fresh()->slug);
    }

    public function test_a_slug_used_by_another_category_is_refused(): void
    {
        $this->update($this->news, ['label' => 'News', 'slug' => 'events'])->assertSessionHasErrors('slug');

        $this->assertSame('news', $this->news->fresh()->slug);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $this->update($this->news, ['label' => 'News', 'slug' => 'news', 'parent_id' => (string) $this->news->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($this->news->fresh()->parent_id);
    }

    public function test_a_category_moves_under_another(): void
    {
        $this->update($this->news, ['label' => 'News', 'slug' => 'news', 'parent_id' => (string) $this->events->id])
            ->assertSessionHasNoErrors();

        $news = $this->news->fresh();
        $this->assertSame($this->events->id, (int) $news->parent_id);
        $this->assertSame(1, (int) $news->depth);
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function update(BlogCategory $category, array $form): \Illuminate\Testing\TestResponse
    {
        return $this->put(route('dixlase-blog::admin.blog.categories.update', ['category' => $category->id]), $form);
    }
}
