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

use App\Support\Content\FileContentColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a post keeps its body, CSS and JavaScript (#193): the database, as
 * every post did so far (0, ContentStorageType::DATABASE), or files (1),
 * published through core's shared layer. The two hashes are what that
 * layer keeps to tell a change made to the files outside the admin; existing
 * rows keep NULL until their first check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plg_dixlase_blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('plg_dixlase_blog_posts', 'storage_type')) {
                $table->tinyInteger('storage_type')->default(0)->after('editor_type');
            }
            if (! Schema::hasColumn('plg_dixlase_blog_posts', FileContentColumns::PUBLISHED_HASH)) {
                FileContentColumns::add($table);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('plg_dixlase_blog_posts', FileContentColumns::PUBLISHED_HASH)) {
            Schema::table('plg_dixlase_blog_posts', function (Blueprint $table) {
                FileContentColumns::drop($table);
            });
        }
        if (Schema::hasColumn('plg_dixlase_blog_posts', 'storage_type')) {
            Schema::table('plg_dixlase_blog_posts', function (Blueprint $table) {
                $table->dropColumn('storage_type');
            });
        }
    }
};
