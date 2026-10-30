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

namespace Plugins\DixlaseBlog\App\Http\Controllers\Admin;

use App\Helpers\LocaleHelper;
use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogTagRequest;
use Plugins\DixlaseBlog\App\Models\BlogTag;

/**
 * The Tags screen: every tag with its number of posts, to add, rename or
 * delete. Tags are otherwise created from the post form.
 */
class DixlaseBlogAdminTagsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;

    public function __construct()
    {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function index(): View
    {
        return view('dixlase-blog::admin.tags.index', array_merge($this->viewParams, [
            'tags' => BlogTag::query()->withCount('posts')->orderBy('name')->get(),
        ]));
    }

    public function store(BlogTagRequest $request): RedirectResponse
    {
        $data = $request->validated();

        BlogTag::create([
            'name' => $data['name'],
            'slug' => BlogTag::freeSlug($data['slug'] ?? null, $data['name']),
            // In the site's language, like a category's name.
            'lang' => LocaleHelper::getSiteDefaultLocale(),
        ]);

        return redirect()
            ->route('dixlase-blog::admin.blog.tags.index')
            ->with('success', __('dixlase-blog::admin.flash.tag_created'));
    }

    /**
     * {tag} is read by name, as in the other blog controllers (see
     * DixlaseBlogAdminPostsController::edit).
     */
    public function update(BlogTagRequest $request): RedirectResponse
    {
        $tag = BlogTag::query()->findOrFail((int) $request->route('tag'));
        $data = $request->validated();

        $tag->update([
            'name' => $data['name'],
            'slug' => BlogTag::freeSlug($data['slug'] ?? null, $data['name'], $tag->id),
        ]);

        return redirect()
            ->route('dixlase-blog::admin.blog.tags.index')
            ->with('success', __('dixlase-blog::admin.flash.tag_updated'));
    }

    /**
     * The tag leaves its posts (the pivot rows cascade); the posts stay.
     */
    public function destroy(Request $request): RedirectResponse
    {
        BlogTag::query()->findOrFail((int) $request->route('tag'))->delete();

        return redirect()
            ->route('dixlase-blog::admin.blog.tags.index')
            ->with('success', __('dixlase-blog::admin.flash.tag_deleted'));
    }
}
