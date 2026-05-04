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

use App\Models\SecuritySetting;
use Illuminate\Support\Facades\Route;
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminPostsController;

$adminUrl = SecuritySetting::get('admin_url', config('security.admin_url'));

// Front-end routes. The front.ip middleware is required to enforce the IP
// filter — omitting it materially weakens security.
Route::middleware(['front.ip'])
    ->group(function () {
        // Public routes (list, detail, category/tag archives, RSS feed) are
        // added in a later phase.
    });

// Admin routes. The admin.ip middleware is required to enforce the admin IP
// filter — omitting it materially weakens security.
Route::prefix($adminUrl)
    ->name('dixlase-blog::admin.')
    ->middleware(['auth:member', 'admin.ip'])
    ->group(function () {
        Route::get('/blog', [DixlaseBlogAdminPostsController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [DixlaseBlogAdminPostsController::class, 'create'])->name('blog.create');
        Route::post('/blog', [DixlaseBlogAdminPostsController::class, 'store'])->name('blog.store');
        Route::get('/blog/{post}/edit', [DixlaseBlogAdminPostsController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{post}', [DixlaseBlogAdminPostsController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{post}', [DixlaseBlogAdminPostsController::class, 'destroy'])->name('blog.destroy');
    });
