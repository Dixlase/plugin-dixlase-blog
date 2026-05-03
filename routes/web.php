<?php

/**
 * This file is part of MySoftware.
 *
 * Copyright (C) 2025 exc-D inc.
 * Website: https://exc-d.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Illuminate\Support\Facades\Route;
use Plugins\BlogPlugin\App\Http\Controllers\admin\AdminBlogPluginController;
use App\Models\SecuritySetting;

// 管理画面のルート。
$adminUrl = SecuritySetting::get('admin_url', config('security.admin_url'));

// フロントエンドのルート。front.ipミドルウェアを適用し、IPアドレスフィルターを必ず通してください。
//　IPアドレスフィルターを通さないとセキュリティリスクが高まります。
Route::middleware(['front.ip'])
    ->group(
        function () {}
    );

// 管理画面のルート。 front.ipミドルウェアを適用し、IPアドレスフィルターを必ず通してください。
//　IPアドレスフィルターを通さないとセキュリティリスクが高まります。
Route::prefix($adminUrl)->name('admin.')
    ->name('blog-plugin::admin.')
    ->middleware(['auth:member', 'admin.ip']) // 認証とIPアドレスフィルタ
    ->group(
        function () {
            // イベント一覧
            Route::get('/blog', [AdminBlogPluginController::class, 'index'])->name('blog.index');
            // イベント作成
            Route::get('/blog/create', [AdminBlogPluginController::class, 'create'])->name('blog.create');
            // イベント保存
            Route::post('/blog/store', [AdminBlogPluginController::class, 'store'])->name('blog.store');
            // ページ編集
            Route::get('/blog/edit/{post}', [AdminBlogPluginController::class, 'edit'])->name('blog.edit');
            // ページ削除
            Route::delete('/blog/delete/{post}', [AdminBlogPluginController::class, 'destroy'])->name('blog.destroy');
        }
    );
