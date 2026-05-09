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

namespace Plugins\DixlaseBlog\App\Http\Controllers\Admin;

use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogCategoryStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogCategoryUpdateRequest;
use Plugins\DixlaseBlog\App\Models\BlogCategory;

class DixlaseBlogAdminCategoriesController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;
    use AuthorizesRequests;

    public function __construct()
    {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function index(): View
    {
        $categories = BlogCategory::query()
            ->withCount('posts')
            ->orderBy('depth')
            ->orderBy('display_order')
            ->orderBy('label')
            ->get();

        return view('dixlase-blog::admin.categories.index', array_merge($this->viewParams, [
            'categories' => $categories,
        ]));
    }

    public function store(BlogCategoryStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['label']);
        $data['display_order'] = $data['display_order'] ?? $this->nextDisplayOrder($data['parent_id'] ?? null);

        try {
            BlogCategory::create($data);
        } catch (DomainException $e) {
            return back()
                ->withInput()
                ->withErrors(['parent_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('dixlase-blog::admin.blog.categories.index')
            ->with('success', __('dixlase-blog::admin.flash.category_created'));
    }

    /**
     * Resolve the {category} route param manually rather than via
     * Laravel's implicit model binding — see DixlaseBlogAdminPostsController::edit
     * for the full reasoning. Short version: under this Brand setup the
     * admin URL ends up with an extra {locale} prefix and implicit
     * binding stops resolving the inner placeholder.
     */
    public function update(BlogCategoryUpdateRequest $request, int $category): RedirectResponse
    {
        $category = BlogCategory::query()->findOrFail($category);

        $data = $request->validated();
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['label'], $category->id);

        try {
            $category->update($data);
        } catch (DomainException $e) {
            return back()
                ->withInput()
                ->withErrors(['parent_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('dixlase-blog::admin.blog.categories.index')
            ->with('success', __('dixlase-blog::admin.flash.category_updated'));
    }

    public function destroy(int $category): RedirectResponse
    {
        $category = BlogCategory::query()->findOrFail($category);

        try {
            $category->delete();
        } catch (QueryException $e) {
            // restrictOnDelete on parent_id rejects deletion when children
            // exist; surface that as a flash error instead of a 500.
            return back()->withErrors([
                'category' => __('dixlase-blog::admin.flash.category_has_children'),
            ]);
        }

        return redirect()
            ->route('dixlase-blog::admin.blog.categories.index')
            ->with('success', __('dixlase-blog::admin.flash.category_deleted'));
    }

    private function resolveSlug(?string $candidate, string $label, ?int $excludeId = null): string
    {
        $base = trim((string) $candidate);
        if ($base === '') {
            $base = Str::slug($label);
            if ($base === '') {
                $base = 'category-'.substr(md5($label.microtime(true)), 0, 8);
            }
        }

        $slug = $base;
        $suffix = 2;
        while (
            BlogCategory::query()
                ->where('slug', $slug)
                ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function nextDisplayOrder(?int $parentId): int
    {
        $max = (int) BlogCategory::query()
            ->where('parent_id', $parentId)
            ->max('display_order');

        return $max + 10;
    }

    /**
     * Persist a drag-and-drop reordering of the category tree.
     *
     * The client walks the DOM after each drop and posts a flat list of
     * { id, parent_id, display_order } in pre-order traversal (parents
     * before children). We save each item via Eloquent so the model's
     * saving / saved hooks fire — saving recomputes depth and cycle-
     * checks, saved cascades the new depth to descendants. Processing
     * order matters: parents must be saved before their children so the
     * children's hook reads a fresh parent depth.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:1000'],
            'items.*.id' => ['required', 'integer', 'exists:plg_dixlase_blog_categories,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:plg_dixlase_blog_categories,id'],
            'items.*.display_order' => ['required', 'integer', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($data): void {
                foreach ($data['items'] as $item) {
                    $category = BlogCategory::query()->find($item['id']);
                    if (! $category) {
                        continue;
                    }
                    $category->parent_id = $item['parent_id'];
                    $category->display_order = (int) $item['display_order'];
                    $category->save();
                }
            });
        } catch (DomainException $e) {
            // Cycle detected by the saving hook — surface a 422 so the
            // client can show the message and revert the drag visually.
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['ok' => true]);
    }
}
