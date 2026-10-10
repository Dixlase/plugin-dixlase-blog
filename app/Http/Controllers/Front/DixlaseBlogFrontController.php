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
 */

declare(strict_types=1);

namespace Plugins\DixlaseBlog\App\Http\Controllers\Front;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;

class DixlaseBlogFrontController extends Controller
{
    /** Posts per page on listing pages. */
    private const PER_PAGE = 12;

    public function index(): View
    {
        $posts = BlogPost::query()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE);

        return view('dixlase-blog::front.index', [
            'posts' => $posts,
        ]);
    }

    /**
     * Route parameters are read by name. DixlaseMultilingual mounts these
     * routes a second time under /{locale}, and Laravel hands controller
     * arguments over by position, so a `string $slug` argument would
     * receive the locale there.
     */
    public function show(Request $request): View
    {
        $post = BlogPost::query()
            ->currentlyVisible()
            ->where('slug', $this->slug($request))
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->firstOrFail();

        return view('dixlase-blog::front.show', [
            'post' => $post,
        ]);
    }

    public function category(Request $request): View
    {
        $category = BlogCategory::query()
            ->where('slug', $this->slug($request))
            ->firstOrFail();

        $posts = $category->posts()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE);

        return view('dixlase-blog::front.archive', [
            'archiveType' => 'category',
            'archiveTerm' => $category,
            'archiveLabel' => $category->localized('label'),
            'archiveLang' => $category->localizedLang('label'),
            'posts' => $posts,
        ]);
    }

    public function tag(Request $request): View
    {
        $tag = BlogTag::query()
            ->where('slug', $this->slug($request))
            ->firstOrFail();

        $posts = $tag->posts()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE);

        return view('dixlase-blog::front.archive', [
            'archiveType' => 'tag',
            'archiveTerm' => $tag,
            'archiveLabel' => $tag->localized('name'),
            'archiveLang' => $tag->localizedLang('name'),
            'posts' => $posts,
        ]);
    }

    private function slug(Request $request): string
    {
        return (string) $request->route('slug');
    }
}
