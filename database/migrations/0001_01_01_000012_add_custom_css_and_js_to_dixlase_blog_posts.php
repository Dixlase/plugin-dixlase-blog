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
 * A post's own CSS and JavaScript, written with the HTML editor by members
 * who may author scripts (BlogScriptAuthoringPolicy) and served next to the
 * post. longText, as content: a large stylesheet must not fail as MySQL 1366.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plg_dixlase_blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('plg_dixlase_blog_posts', 'custom_css')) {
                $table->longText('custom_css')->nullable()->after('content');
            }
            if (! Schema::hasColumn('plg_dixlase_blog_posts', 'custom_js')) {
                $table->longText('custom_js')->nullable()->after('custom_css');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['custom_css', 'custom_js'],
            fn (string $column) => Schema::hasColumn('plg_dixlase_blog_posts', $column),
        ));
        if ($columns !== []) {
            Schema::table('plg_dixlase_blog_posts', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
