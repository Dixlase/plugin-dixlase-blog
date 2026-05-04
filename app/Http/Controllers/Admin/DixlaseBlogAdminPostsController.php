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

use App\Helpers\AdminHelper;
use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostUpdateRequest;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogPostService;

class DixlaseBlogAdminPostsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;
    use AuthorizesRequests;

    public function __construct(private readonly BlogPostService $postService)
    {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function index(): View
    {
        $posts = BlogPost::query()
            ->with(['categories', 'tags', 'author'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dixlase-blog::admin.posts.index', array_merge($this->viewParams, [
            'posts' => $posts,
        ]));
    }

    public function create(): View
    {
        return view('dixlase-blog::admin.posts.create', array_merge($this->viewParams, [
            'post' => new BlogPost(),
            'categoryTree' => $this->loadCategoryTree(),
            'selectedCategoryIds' => [],
            'tagNames' => [],
        ]));
    }

    public function store(BlogPostStoreRequest $request): RedirectResponse
    {
        $post = $this->postService->create(
            $request->validated(),
            AdminHelper::getMember()?->id,
        );

        return redirect()
            ->route('dixlase-blog::admin.blog.edit', ['post' => $post->id])
            ->with('success', __('dixlase-blog::admin.flash.post_created'));
    }

    public function edit(BlogPost $post): View
    {
        $post->load(['categories', 'tags']);

        return view('dixlase-blog::admin.posts.edit', array_merge($this->viewParams, [
            'post' => $post,
            'categoryTree' => $this->loadCategoryTree(),
            'selectedCategoryIds' => $post->categories->pluck('id')->all(),
            'tagNames' => $post->tags->pluck('name')->all(),
        ]));
    }

    public function update(BlogPostUpdateRequest $request, BlogPost $post): RedirectResponse
    {
        $this->postService->update(
            $post,
            $request->validated(),
            AdminHelper::getMember()?->id,
        );

        return redirect()
            ->route('dixlase-blog::admin.blog.edit', ['post' => $post->id])
            ->with('success', __('dixlase-blog::admin.flash.post_updated'));
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();

        return redirect()
            ->route('dixlase-blog::admin.blog.index')
            ->with('success', __('dixlase-blog::admin.flash.post_trashed'));
    }

    public function trash(): View
    {
        $posts = BlogPost::onlyTrashed()
            ->with(['categories', 'tags', 'author'])
            ->orderByDesc('deleted_at')
            ->paginate(20);

        return view('dixlase-blog::admin.posts.trash', array_merge($this->viewParams, [
            'posts' => $posts,
        ]));
    }

    public function restore(int $id): RedirectResponse
    {
        $post = BlogPost::onlyTrashed()->findOrFail($id);
        $post->restore();

        return redirect()
            ->route('dixlase-blog::admin.blog.trash')
            ->with('success', __('dixlase-blog::admin.flash.post_restored'));
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $post = BlogPost::onlyTrashed()->findOrFail($id);
        $post->forceDelete();

        return redirect()
            ->route('dixlase-blog::admin.blog.trash')
            ->with('success', __('dixlase-blog::admin.flash.post_deleted'));
    }

    /**
     * Flat list of categories with depth info, for rendering an indented
     * <select> or checkbox tree in the admin form.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BlogCategory>
     */
    private function loadCategoryTree(): \Illuminate\Database\Eloquent\Collection
    {
        return BlogCategory::query()
            ->orderBy('depth')
            ->orderBy('display_order')
            ->orderBy('label')
            ->get();
    }
}
