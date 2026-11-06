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

use App\Enums\ContentStatus;
use App\Enums\MemberRole;
use App\Models\Member;
use App\Services\PermissionRegistry;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * Who may publish posts.
 *
 * Publishing is its own permission, `blog.publish` in config/admin/roles.php,
 * so a role can be allowed to write drafts without being allowed to put them
 * on the site. A member without it may save a post only as a draft, and may
 * not change a post that is live or scheduled -- saving it, publishing its
 * files, restoring a revision, trashing it or restoring it from the trash --
 * because each of those changes what the site shows.
 */
final class BlogPublishPolicy
{
    public const PLUGIN = 'DixlaseBlog';

    public const MENU_KEY = 'blog.publish';

    /** Statuses that put a post on the site, now or at its publish time. */
    public const PUBLISHING_STATUSES = ['published', 'scheduled'];

    public static function canPublish(?Member $member): bool
    {
        if ($member === null || ! $member->role instanceof MemberRole) {
            return false;
        }

        if ($member->role === MemberRole::SUPER_ADMIN) {
            return true;
        }

        return PermissionRegistry::canAccessPlugin(self::PLUGIN, self::MENU_KEY, $member->role);
    }

    /**
     * Whether the post is on the site, or will be at its publish time.
     */
    public static function isLive(BlogPost $post): bool
    {
        return $post->status === ContentStatus::PUBLISHED || $post->status === ContentStatus::SCHEDULED;
    }

    /**
     * Whether this member may change the post at all: anyone who may edit
     * posts may change a draft; a live or scheduled post needs the right to
     * publish.
     */
    public static function canChange(?Member $member, BlogPost $post): bool
    {
        return ! self::isLive($post) || self::canPublish($member);
    }

    /**
     * Status slugs this member may choose.
     *
     * @return list<string>
     */
    public static function allowedStatusSlugs(?Member $member): array
    {
        $all = array_map(fn (ContentStatus $case): string => $case->slug(), ContentStatus::cases());

        return self::canPublish($member)
            ? $all
            : array_values(array_diff($all, self::PUBLISHING_STATUSES));
    }
}
