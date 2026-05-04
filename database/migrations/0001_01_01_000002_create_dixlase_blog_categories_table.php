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

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plg_dixlase_blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('slug', 200)->unique();
            // Self-referential adjacency-list parent. Restricting on delete
            // forces explicit reparenting of children before a category can
            // be deleted, preventing orphaned subtrees.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('plg_dixlase_blog_categories')
                ->restrictOnDelete();
            // Denormalized depth (0 for top-level) maintained by the model
            // so listing queries don't need a recursive CTE.
            $table->unsignedSmallInteger('depth')->default(0);
            // Sort order within the same parent. Lower values display first.
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plg_dixlase_blog_categories');
    }
};
