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
 * Comments on posts. One table for every language of a post: comments are
 * not translated. A reply points at its parent comment, to any depth.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plg_dixlase_blog_comments')) {
            return;
        }

        Schema::create('plg_dixlase_blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')
                ->constrained('plg_dixlase_blog_posts')
                ->cascadeOnDelete();
            // Replies are removed with the comment they answer.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('plg_dixlase_blog_comments')
                ->cascadeOnDelete();
            // Set when an admin comments as the site; visitors have none.
            $table->foreignId('member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();
            $table->string('author_name', 100);
            // Never shown on the site.
            $table->string('author_email')->nullable();
            $table->text('body');
            // pending, published or spam (BlogComment::STATUS_*).
            $table->string('status', 20)->default('pending');
            // The page language the comment was written on, for lang="".
            $table->string('lang', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            // The trash; a trashed comment can be restored.
            $table->softDeletes();

            $table->index(['post_id', 'status', 'created_at'], 'plg_dxl_blog_comments_post_status_idx');
            $table->index(['status', 'created_at'], 'plg_dxl_blog_comments_status_idx');
            $table->index('author_email', 'plg_dxl_blog_comments_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plg_dixlase_blog_comments');
    }
};
