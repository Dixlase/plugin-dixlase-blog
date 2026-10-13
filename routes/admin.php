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
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminCategoriesController;
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminCommentsController;
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminPostRevisionsController;
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminPostsController;
use Plugins\DixlaseBlog\App\Http\Controllers\Admin\DixlaseBlogAdminSettingsController;

/*
|--------------------------------------------------------------------------
| Admin routes (loaded by core's PluginServiceProvider)
|--------------------------------------------------------------------------
|
| Core loads this file once, outside any {locale} group, under the admin
| URL prefix and with the same middleware as core's admin screens
| (admin.ip, auth:member, member.active, verified, the admin lockdown,
| no-cache and plugin.admin.access, which applies config/admin/roles.php;
| without one, ADMIN and above).
|
| Route ordering matters: the literal segments /blog/trash,
| /blog/categories, /blog/comments and /blog/settings are declared before the /blog/{post}/... patterns so
| that "trash" or "categories" is not bound as a post id.
|
*/

Route::name('dixlase-blog::admin.')
    ->group(function () {
        // ===== Posts: collection-level routes =====
        Route::get('/blog', [DixlaseBlogAdminPostsController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [DixlaseBlogAdminPostsController::class, 'create'])->name('blog.create');
        Route::post('/blog', [DixlaseBlogAdminPostsController::class, 'store'])->name('blog.store');

        // ===== Live preview =====
        // POST: editor pushes { content, editor_type } here on every
        // debounced edit; response is { html } that the editor postMessages
        // into the iframe shell loaded from the GET below.
        // Declared before /blog/{post}/edit so "preview" / "preview-frame"
        // are not bound as a BlogPost id.
        Route::post('/blog/preview', [DixlaseBlogAdminPostsController::class, 'preview'])->name('blog.preview');
        Route::get('/blog/preview-frame/{post?}', [DixlaseBlogAdminPostsController::class, 'previewFrame'])
            ->whereNumber('post')
            ->name('blog.previewFrame');

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
                // Reorder must come before /{category} so "reorder" is not bound as a category id.
                Route::post('/reorder', [DixlaseBlogAdminCategoriesController::class, 'reorder'])->name('reorder');
                Route::put('/{category}', [DixlaseBlogAdminCategoriesController::class, 'update'])->name('update');
                Route::delete('/{category}', [DixlaseBlogAdminCategoriesController::class, 'destroy'])->name('destroy');
            });

        // ===== Comments =====
        // Before /blog/{post} so "comments" is not bound as a post id. All
        // actions are POST so one row's buttons can share the bulk form's
        // layout (see admin/comments/index.blade.php).
        Route::prefix('/blog/comments')
            ->name('blog.comments.')
            ->group(function () {
                Route::get('/', [DixlaseBlogAdminCommentsController::class, 'index'])->name('index');
                Route::post('/bulk', [DixlaseBlogAdminCommentsController::class, 'bulk'])->name('bulk');
                Route::post('/{comment}/status', [DixlaseBlogAdminCommentsController::class, 'status'])
                    ->whereNumber('comment')
                    ->name('status');
                Route::post('/{comment}/reply', [DixlaseBlogAdminCommentsController::class, 'reply'])
                    ->whereNumber('comment')
                    ->name('reply');
                Route::post('/{comment}/trash', [DixlaseBlogAdminCommentsController::class, 'destroy'])
                    ->whereNumber('comment')
                    ->name('destroy');
                Route::post('/{comment}/restore', [DixlaseBlogAdminCommentsController::class, 'restore'])
                    ->whereNumber('comment')
                    ->name('restore');
                Route::post('/{comment}/delete', [DixlaseBlogAdminCommentsController::class, 'forceDelete'])
                    ->whereNumber('comment')
                    ->name('forceDelete');
            });

        // ===== Settings =====
        // Before /blog/{post} so "settings" is not bound as a post id.
        Route::get('/blog/settings', [DixlaseBlogAdminSettingsController::class, 'edit'])->name('blog.settings.edit');
        Route::put('/blog/settings', [DixlaseBlogAdminSettingsController::class, 'update'])->name('blog.settings.update');

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
