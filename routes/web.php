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

use App\Http\Middleware\ContentSecurityPolicy;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogCommentController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogCustomAssetController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFeedController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

// Front-end routes only; the admin routes are in routes/admin.php. With
// locale URLs on, this file is also loaded under /{locale}. The front.ip middleware is required to enforce the IP
// filter — omitting it materially weakens security.
//
// The prefix ("blog" by default) is the route_slug setting, read when the
// routes are registered; core's RouteSlugRegistry keeps it apart from
// other plugins' prefixes (see DixlaseBlogServiceProvider).
//
// Route ordering matters: literal paths /blog/feed.xml, /blog/category/...,
// /blog/tag/... and /blog/search are declared before the catch-all
// /blog/{slug} so the catch-all does not bind "feed.xml" or "search" as a
// post slug. Date archives use two-digit months and days (/blog/2026/04).
$blog = '/'.app(BlogSettings::class)->routeSlug();

Route::middleware(['front.ip'])
    ->name('dixlase-blog::front.')
    ->group(function () use ($blog) {
        Route::get($blog, [DixlaseBlogFrontController::class, 'index'])->name('index');
        Route::get($blog.'/feed.xml', [DixlaseBlogFeedController::class, 'feed'])->name('feed');
        Route::get($blog.'/category/{slug}', [DixlaseBlogFrontController::class, 'category'])->name('category');
        Route::get($blog.'/tag/{slug}', [DixlaseBlogFrontController::class, 'tag'])->name('tag');
        Route::get($blog.'/search', [DixlaseBlogFrontController::class, 'search'])->name('search');
        Route::get($blog.'/{year}/{month}', [DixlaseBlogFrontController::class, 'date'])
            ->where(['year' => '[0-9]{4}', 'month' => '0[1-9]|1[0-2]'])
            ->name('month');
        Route::get($blog.'/{year}/{month}/{day}', [DixlaseBlogFrontController::class, 'date'])
            ->where(['year' => '[0-9]{4}', 'month' => '0[1-9]|1[0-2]', 'day' => '0[1-9]|[12][0-9]|3[01]'])
            ->name('day');
        Route::post($blog.'/{slug}/comments', [DixlaseBlogCommentController::class, 'store'])
            ->middleware('throttle:'.DixlaseBlogServiceProvider::COMMENT_RATE_LIMITER)
            ->name('comment');
        // A post's own CSS and JavaScript (HTML posts). Outside the content
        // security policy middleware, as DixlasePages serves a page's: the
        // response is a file, not a document.
        Route::get($blog.'/{slug}/custom-style.css', [DixlaseBlogCustomAssetController::class, 'style'])
            ->name('custom-style')
            ->withoutMiddleware([ContentSecurityPolicy::class]);
        Route::get($blog.'/{slug}/custom-script.js', [DixlaseBlogCustomAssetController::class, 'script'])
            ->name('custom-script')
            ->withoutMiddleware([ContentSecurityPolicy::class]);
        Route::get($blog.'/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('show');
    });
