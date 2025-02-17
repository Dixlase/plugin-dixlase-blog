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

// フロントエンドのルート。front.ipミドルウェアを適用し、IPアドレスフィルターを必ず通してください。
//　IPアドレスフィルターを通さないとセキュリティリスクが高まります。
Route::middleware(['front.ip'])
    ->group(function () {

    }
);

// 管理画面のルート。 front.ipミドルウェアを適用し、IPアドレスフィルターを必ず通してください。
//　IPアドレスフィルターを通さないとセキュリティリスクが高まります。
Route::prefix($adminUrl)->name('admin.')
    ->name('blog-plugin::admin.')
    ->middleware(['auth:member', 'admin.ip']) // 認証とIPアドレスフィルタ
    ->group(function () {

    }
);
