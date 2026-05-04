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
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminCategoriesController;
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminPostRevisionsController;
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminPostsController;

$adminUrl = SecuritySetting::get('admin_url', config('security.admin_url'));

// Front-end routes. The front.ip middleware is required to enforce the IP
// filter — omitting it materially weakens security.
Route::middleware(['front.ip'])
    ->group(function () {
        // Public routes (list, detail, category/tag archives, RSS feed) are
        // added in Phase 7.
    });

// Admin routes. The admin.ip middleware is required to enforce the admin IP
// filter — omitting it materially weakens security.
//
// Route ordering matters here: the literal segments /blog/trash and
// /blog/categories are declared *before* the /blog/{post}/... patterns so
// that Laravel matches the literal paths first instead of binding "trash"
// or "categories" as a BlogPost id.
Route::prefix($adminUrl)
    ->name('dixlase-blog::admin.')
    ->middleware(['auth:member', 'admin.ip'])
    ->group(function () {
        // ===== Posts: collection-level routes =====
        Route::get('/blog', [DixlaseBlogAdminPostsController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [DixlaseBlogAdminPostsController::class, 'create'])->name('blog.create');
        Route::post('/blog', [DixlaseBlogAdminPostsController::class, 'store'])->name('blog.store');

        // ===== Trash =====
        Route::get('/blog/trash', [DixlaseBlogAdminPostsController::class, 'trash'])->name('blog.trash');
        Route::post('/blog/trash/{id}/restore', [DixlaseBlogAdminPostsController::class, 'restore'])
            ->whereNumber('id')
            ->name('blog.restore');
        Route::delete('/blog/trash/{id}', [DixlaseBlogAdminPostsController::class, 'forceDelete'])
            ->whereNumber('id')
            ->name('blog.forceDelete');

        // ===== Categories =====
        Route::prefix('/blog/categories')
            ->name('blog.categories.')
            ->group(function () {
                Route::get('/', [DixlaseBlogAdminCategoriesController::class, 'index'])->name('index');
                Route::post('/', [DixlaseBlogAdminCategoriesController::class, 'store'])->name('store');
                Route::put('/{category}', [DixlaseBlogAdminCategoriesController::class, 'update'])->name('update');
                Route::delete('/{category}', [DixlaseBlogAdminCategoriesController::class, 'destroy'])->name('destroy');
            });

        // ===== Posts: item-level routes =====
        Route::get('/blog/{post}/edit', [DixlaseBlogAdminPostsController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{post}', [DixlaseBlogAdminPostsController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{post}', [DixlaseBlogAdminPostsController::class, 'destroy'])->name('blog.destroy');

        // ===== Revisions =====
        Route::prefix('/blog/{post}/revisions')
            ->name('blog.revisions.')
            ->group(function () {
                Route::get('/', [DixlaseBlogAdminPostRevisionsController::class, 'index'])->name('index');
                Route::get('/{revisionId}', [DixlaseBlogAdminPostRevisionsController::class, 'show'])
                    ->whereNumber('revisionId')
                    ->name('show');
                Route::post('/{revisionId}/restore', [DixlaseBlogAdminPostRevisionsController::class, 'restore'])
                    ->whereNumber('revisionId')
                    ->name('restore');
                Route::post('/{revisionId}/protect', [DixlaseBlogAdminPostRevisionsController::class, 'toggleProtection'])
                    ->whereNumber('revisionId')
                    ->name('protect');
                Route::post('/{revisionId}/note', [DixlaseBlogAdminPostRevisionsController::class, 'updateNote'])
                    ->whereNumber('revisionId')
                    ->name('note');
            });
    });
