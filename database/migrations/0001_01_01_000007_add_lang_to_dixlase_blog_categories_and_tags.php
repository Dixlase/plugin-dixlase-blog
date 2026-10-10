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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source language of category and tag names. Other languages are
 * DixlaseMultilingual overlays, as for posts. Null means the site's
 * default locale.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['plg_dixlase_blog_categories', 'plg_dixlase_blog_tags'] as $name) {
            if (! Schema::hasColumn($name, 'lang')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->string('lang', 10)->nullable()->after('slug');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['plg_dixlase_blog_categories', 'plg_dixlase_blog_tags'] as $name) {
            if (Schema::hasColumn($name, 'lang')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('lang');
                });
            }
        }
    }
};
