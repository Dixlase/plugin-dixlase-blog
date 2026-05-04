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
        Schema::create('plg_dixlase_blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 200);
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            // Editor type (1=gui, 2=markdown, 3=html, 4=blade) — see App\Enums\ContentEditorType
            $table->tinyInteger('editor_type')->default(1);
            // Status (0=draft, 1=published, 2=scheduled) — see App\Enums\ContentStatus
            $table->tinyInteger('status')->default(0);
            // Language code; pre-provisioned for future DixlaseMultilingual integration.
            // Defaults to the site primary locale at write time.
            $table->string('lang', 10)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('members')->nullOnDelete();
            // Publication time. For status=published, when it went live.
            // For status=scheduled, the future time at which it should go live.
            $table->timestamp('published_at')->nullable();
            // Optional auto-unpublish time.
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Slug must be unique among non-trashed posts; trashed posts can
            // coexist (different deleted_at values).
            $table->unique(['slug', 'deleted_at'], 'plg_dxl_blog_posts_slug_del_unique');
            $table->index('lang');
            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plg_dixlase_blog_posts');
    }
};
