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
        Schema::create('plg_dixlase_blog_post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')
                ->constrained('plg_dixlase_blog_posts')
                ->cascadeOnDelete();
            // Full snapshot of revisionable fields (title, slug, content,
            // excerpt, featured_media_id, editor_type, status, lang,
            // published_at, closed_at, plus category_ids and tag_ids).
            $table->json('snapshot');
            // Revision type: auto (autosave), manual (explicit save),
            // restore_backup (snapshot taken right before a restore).
            // Mirrors App\Services\RevisionService constants.
            $table->string('type', 20)->default('auto');
            $table->string('note')->nullable();
            // Protected revisions are excluded from auto-pruning when
            // content.revision.retention_count is exceeded.
            $table->boolean('is_protected')->default(false);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['post_id', 'created_at'], 'plg_dxl_blog_post_rev_post_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plg_dixlase_blog_post_revisions');
    }
};
