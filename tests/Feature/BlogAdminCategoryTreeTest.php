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
 * The category tree: children made under a row, the three-level limit
 * (BlogCategory::MAX_DEPTH) on every way in, and "Move up a level".
 */
class BlogAdminCategoryTreeTest extends TestCase
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

        $this->actingAs(Member::factory()->create(['role' => MemberRole::ADMIN, 'status' => 1]));
    }

    public function test_a_child_and_a_grandchild_are_made_under_their_rows(): void
    {
        $parent = $this->category('Parent');

        $this->store(['label' => 'Child', 'parent_id' => (string) $parent->id, '_form' => 'child-'.$parent->id])
            ->assertSessionHasNoErrors();
        $child = BlogCategory::query()->where('label', 'Child')->firstOrFail();
        $this->assertSame(1, $child->depth);

        $this->store(['label' => 'Grandchild', 'parent_id' => (string) $child->id])->assertSessionHasNoErrors();
        $this->assertSame(2, BlogCategory::query()->where('label', 'Grandchild')->firstOrFail()->depth);
    }

    public function test_a_fourth_level_is_refused_and_the_form_opens_again(): void
    {
        $grandchild = $this->category('Grandchild', $this->category('Child', $this->category('Parent')));

        $this->store(['label' => 'Too deep', 'parent_id' => (string) $grandchild->id, '_form' => 'child-'.$grandchild->id])
            ->assertSessionHasErrors(['parent_id' => BlogCategory::tooDeepMessage()])
            ->assertSessionHasInput('_form', 'child-'.$grandchild->id);

        $this->assertFalse(BlogCategory::query()->where('label', 'Too deep')->exists());
    }

    public function test_a_category_with_children_cannot_be_moved_below_the_limit(): void
    {
        $middle = $this->category('Middle', $this->category('Top'));
        $branch = $this->category('Branch');
        $this->category('Leaf', $branch);

        // Branch would sit at depth 2 and its child at 3.
        $this->put(route('dixlase-blog::admin.blog.categories.update', ['category' => $branch->id]), [
            'label' => 'Branch',
            'slug' => 'branch',
            'parent_id' => (string) $middle->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull($branch->fresh()->parent_id);
    }

    public function test_reorder_refuses_a_fourth_level_and_keeps_the_tree(): void
    {
        $top = $this->category('Top');
        $middle = $this->category('Middle', $top);
        $branch = $this->category('Branch');
        $leaf = $this->category('Leaf', $branch);

        $this->postJson(route('dixlase-blog::admin.blog.categories.reorder'), ['items' => [
            ['id' => $top->id, 'parent_id' => null, 'display_order' => 10],
            ['id' => $middle->id, 'parent_id' => $top->id, 'display_order' => 10],
            ['id' => $branch->id, 'parent_id' => $middle->id, 'display_order' => 20],
            ['id' => $leaf->id, 'parent_id' => $branch->id, 'display_order' => 10],
        ]])->assertStatus(422)->assertJson(['error' => BlogCategory::tooDeepMessage()]);

        $this->assertNull($branch->fresh()->parent_id);
        $this->assertSame(1, $leaf->fresh()->depth);
    }

    public function test_a_tree_deeper_than_the_limit_still_saves_a_rename(): void
    {
        $grandchild = $this->category('Grandchild', $this->category('Child', $this->category('Parent')));
        // Made before the limit existed: written past the model hooks.
        $deep = BlogCategory::query()->create(['label' => 'Deep', 'slug' => 'deep']);
        BlogCategory::query()->whereKey($deep->id)->update(['parent_id' => $grandchild->id, 'depth' => 3]);

        $this->put(route('dixlase-blog::admin.blog.categories.update', ['category' => $deep->id]), [
            'label' => 'Deeper',
            'slug' => 'deep',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Deeper', $deep->fresh()->label);
    }

    public function test_move_up_places_the_category_after_its_former_parent(): void
    {
        $first = $this->category('First', null, 10);
        $second = $this->category('Second', null, 20);
        $child = $this->category('Child', $first);
        $grandchild = $this->category('Grandchild', $child);

        $this->outdent($grandchild)->assertSessionHas('success');
        $grandchild->refresh();
        $this->assertSame($first->id, (int) $grandchild->parent_id);
        $this->assertSame(1, $grandchild->depth);

        $this->outdent($child)->assertSessionHas('success');
        $this->assertNull($child->fresh()->parent_id);
        $this->assertSame(
            ['First', 'Child', 'Second'],
            BlogCategory::query()->whereNull('parent_id')->orderBy('display_order')->pluck('label')->all(),
        );
        // The grandchild came along and is a child now.
        $this->assertSame(1, $grandchild->fresh()->depth);
        $this->assertSame($second->id, BlogCategory::query()->whereNull('parent_id')->orderByDesc('display_order')->value('id'));
    }

    public function test_move_up_leaves_a_top_level_category_where_it_is(): void
    {
        $top = $this->category('Top');

        $this->outdent($top)->assertRedirect(route('dixlase-blog::admin.blog.categories.index'));

        $this->assertNull($top->fresh()->parent_id);
    }

    private function category(string $label, ?BlogCategory $parent = null, int $order = 10): BlogCategory
    {
        return BlogCategory::query()->create([
            'label' => $label,
            'slug' => strtolower(str_replace(' ', '-', $label)),
            'parent_id' => $parent?->id,
            'display_order' => $order,
        ]);
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function store(array $form): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('dixlase-blog::admin.blog.categories.store'), $form);
    }

    private function outdent(BlogCategory $category): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('dixlase-blog::admin.blog.categories.outdent', ['category' => $category->id]));
    }
}
