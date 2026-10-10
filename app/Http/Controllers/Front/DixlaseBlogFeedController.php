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

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Plugins\DixlaseBlog\App\Models\BlogPost;

class DixlaseBlogFeedController extends Controller
{
    /** Number of items to include in the RSS feed. */
    private const ITEM_COUNT = 20;

    public function feed(): Response
    {
        $posts = BlogPost::query()
            ->currentlyVisible()
            ->with(['author'])
            ->orderByDesc('published_at')
            ->limit(self::ITEM_COUNT)
            ->get();

        $xml = view('dixlase-blog::front.feed', [
            'posts' => $posts,
            'siteName' => config('app.name'),
            'siteUrl' => url('/'),
            'feedUrl' => route('dixlase-blog::front.feed'),
            'blogUrl' => route('dixlase-blog::front.index'),
            'language' => str_replace('_', '-', app()->getLocale()),
        ])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
