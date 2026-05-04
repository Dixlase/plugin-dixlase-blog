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
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
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

    public function update(BlogCategoryUpdateRequest $request, BlogCategory $category): RedirectResponse
    {
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

    public function destroy(BlogCategory $category): RedirectResponse
    {
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
}
