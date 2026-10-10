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

use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFeedController;
use Plugins\DixlaseBlog\App\Http\Controllers\Front\DixlaseBlogFrontController;

// Front-end routes only; the admin routes are in routes/admin.php. With
// DixlaseMultilingual's locale URLs on, this file is also loaded under
// /{locale}. The front.ip middleware is required to enforce the IP
// filter — omitting it materially weakens security.
//
// Route ordering matters: literal paths /blog/feed.xml, /blog/category/...
// and /blog/tag/... are declared before the catch-all /blog/{slug} so the
// catch-all does not bind "feed.xml" or "category" as a post slug.
Route::middleware(['front.ip'])
    ->name('dixlase-blog::front.')
    ->group(function () {
        Route::get('/blog', [DixlaseBlogFrontController::class, 'index'])->name('index');
        Route::get('/blog/feed.xml', [DixlaseBlogFeedController::class, 'feed'])->name('feed');
        Route::get('/blog/category/{slug}', [DixlaseBlogFrontController::class, 'category'])->name('category');
        Route::get('/blog/tag/{slug}', [DixlaseBlogFrontController::class, 'tag'])->name('tag');
        Route::get('/blog/{slug}', [DixlaseBlogFrontController::class, 'show'])->name('show');
    });
