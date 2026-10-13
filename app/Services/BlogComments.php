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

namespace Plugins\DixlaseBlog\App\Services;

use App\Contracts\PluginIntegration\PrivacyPolicyProviderInterface;
use App\Enums\MemberRole;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Support\BlogSettings;
use Throwable;

/**
 * Comments on a post: whether the post takes them, the published ones as
 * a tree, and the state a new one starts in.
 */
class BlogComments
{
    public function __construct(private readonly BlogSettings $settings)
    {
    }

    /**
     * Whether the post takes new comments now. A post's own open / closed
     * wins; "inherit" follows the blog setting and its closing period.
     */
    public function isOpen(BlogPost $post): bool
    {
        if (! $post->isCurrentlyVisible()) {
            return false;
        }

        return match ($post->comment_status) {
            'open' => true,
            'closed' => false,
            default => $this->settings->commentsEnabled() && ! $this->periodEnded($post),
        };
    }

    /**
     * Whether the post page shows the comments section: the form, or
     * comments already published.
     */
    public function isShown(BlogPost $post): bool
    {
        return $this->isOpen($post) || $this->publishedCount($post) > 0;
    }

    public function publishedCount(BlogPost $post): int
    {
        return $post->comments()->published()->count();
    }

    /**
     * Published comments, oldest first, each with its published replies.
     * A reply under a comment that is not published is not shown: it
     * would answer something the reader cannot see.
     *
     * @return list<array{comment: BlogComment, replies: list<mixed>}>
     */
    public function tree(BlogPost $post): array
    {
        $comments = $post->comments()
            ->published()
            ->with('member')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $this->branch($comments->groupBy(fn (BlogComment $c) => (int) $c->parent_id), 0);
    }

    /**
     * The state a new comment starts in. An admin's comment is the site's
     * and is published; a visitor's waits for approval when the blog says
     * so or when it carries more links than allowed.
     */
    public function initialStatus(string $body, bool $bySite): string
    {
        if ($bySite) {
            return BlogComment::STATUS_PUBLISHED;
        }

        if ($this->settings->commentsNeedApproval() || $this->linkCount($body) > $this->settings->commentLinkLimit()) {
            return BlogComment::STATUS_PENDING;
        }

        return BlogComment::STATUS_PUBLISHED;
    }

    /**
     * The signed-in member who comments as the site: an editor or above.
     * Anyone else comments as a visitor.
     */
    public function siteCommenter(): ?Member
    {
        $member = Auth::guard('member')->user();

        return $member instanceof Member && $member->role instanceof MemberRole && $member->role->canAccess(MemberRole::EDITOR)
            ? $member
            : null;
    }

    /**
     * The site's privacy notice, when a plugin such as DixlaseLegal
     * provides one.
     */
    public function privacyPolicyUrl(): ?string
    {
        if (! app()->bound(PrivacyPolicyProviderInterface::class)) {
            return null;
        }

        try {
            $provider = app(PrivacyPolicyProviderInterface::class);

            return $provider->isPrivacyPolicyEnabled() ? $provider->getPrivacyPolicyUrl() : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function linkCount(string $body): int
    {
        return preg_match_all('~https?://|\bwww\.~i', $body);
    }

    private function periodEnded(BlogPost $post): bool
    {
        $days = $this->settings->commentsCloseAfterDays();

        return $days > 0
            && $post->published_at !== null
            && $post->published_at->copy()->addDays($days)->isPast();
    }

    /**
     * @param  Collection<int, Collection<int, BlogComment>>  $byParent
     * @return list<array{comment: BlogComment, replies: list<mixed>}>
     */
    private function branch(Collection $byParent, int $parentId): array
    {
        return $byParent->get($parentId, collect())
            ->map(fn (BlogComment $comment) => [
                'comment' => $comment,
                'replies' => $this->branch($byParent, (int) $comment->id),
            ])
            ->values()
            ->all();
    }
}
