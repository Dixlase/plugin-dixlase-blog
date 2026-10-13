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
declare(strict_types=1);

namespace Plugins\DixlaseBlog\App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * Tells an admin about a new visitor comment, with a link to the
 * comments screen.
 */
class BlogCommentNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public BlogComment $comment,
        public BlogPost $post,
        public string $adminUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __($this->comment->isPublished()
                ? 'dixlase-blog::admin.comment_mail.subject_published'
                : 'dixlase-blog::admin.comment_mail.subject_pending', ['title' => $this->post->title]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'dixlase-blog::emails.comment-admin');
    }
}
