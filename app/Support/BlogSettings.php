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

namespace Plugins\DixlaseBlog\App\Support;

use App\Helpers\ConfigHelper;
use Plugins\DixlaseBlog\App\Models\BlogSetting;

/**
 * The blog's settings with their defaults and types. Values are read once
 * per request and kept on this singleton.
 */
class BlogSettings
{
    /**
     * The settings screens, one per area, in the order of the admin menu.
     * Each has a route (blog.settings.<screen>) and a view of that name.
     */
    public const SCREENS = ['listing', 'sidebar', 'share', 'comments'];

    public const POSTS_PER_PAGE_MIN = 1;

    public const POSTS_PER_PAGE_MAX = 100;

    /** How the blog's top page shows posts: cards, or the latest in full. */
    public const INDEX_LAYOUTS = ['cards', 'full'];

    public const SIDEBAR_POSITIONS = ['right', 'left'];

    /** Sidebar widgets, in their default order. */
    public const SIDEBAR_WIDGETS = ['search', 'categories', 'recent', 'archives', 'calendar', 'tags'];

    public const RECENT_POSTS_MIN = 1;

    public const RECENT_POSTS_MAX = 20;

    public const ROUTE_SLUG_DEFAULT = 'blog';

    /** Lower-case letters, digits and hyphens, starting with a letter or digit. */
    public const ROUTE_SLUG_PATTERN = '/^[a-z0-9][a-z0-9\-]*$/';

    public const ROUTE_SLUG_MAX = 50;

    /** Share buttons on the post page, in display order. */
    public const SHARE_SERVICES = ['x', 'facebook', 'line', 'copy'];

    /** A new comment is published at once, or held until an admin approves it. */
    public const COMMENT_MODERATION = ['approval', 'immediate'];

    /** Comments close this many days after publishing; 0 never closes them. */
    public const COMMENTS_CLOSE_AFTER_DAYS_MAX = 3650;

    /** Days a commenter's IP address is kept; 0 does not store it. */
    public const COMMENT_IP_RETENTION_DAYS_MAX = 3650;

    /** A comment with more links than this is held for approval. */
    public const COMMENT_LINK_LIMIT_MAX = 50;

    /** Comments one IP address may post within the rate limit period. */
    public const COMMENT_RATE_MAX_ATTEMPTS_MAX = 100;

    /** The rate limit period, in minutes (one day at most). */
    public const COMMENT_RATE_DECAY_MINUTES_MAX = 1440;

    /**
     * Every setting and its default, as stored (strings). Core inserts the
     * missing rows on install and update; see DixlaseBlogServiceProvider.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        // The first path segment of every public blog URL.
        'route_slug' => 'blog',
        // Ten pages evenly in the two-column cards and keeps a page of
        // posts in full to a readable length.
        'posts_per_page' => '10',
        'index_layout' => 'cards',
        'sidebar_enabled' => '1',
        'sidebar_position' => 'right',
        // JSON list of {"key": ..., "enabled": ...} in display order.
        'sidebar_widgets' => '[{"key":"search","enabled":true},{"key":"categories","enabled":true},{"key":"recent","enabled":true},{"key":"archives","enabled":true},{"key":"calendar","enabled":true},{"key":"tags","enabled":true}]',
        'sidebar_recent_count' => '5',
        // JSON list of the share buttons shown; none hides the row.
        'share_services' => '["x","facebook","line","copy"]',
        // Off until the site turns them on: comments collect personal data.
        'comments_enabled' => '0',
        'comment_moderation' => 'approval',
        'comments_close_after_days' => '0',
        'comment_link_limit' => '2',
        // The comment form takes this many posts per period from one IP
        // address (the throttle in routes/web.php).
        'comment_rate_max_attempts' => '5',
        'comment_rate_decay_minutes' => '10',
        // E-mail an admin about each new visitor comment.
        'comment_notify' => '1',
        // Empty: the site's system admin address (core setting).
        'comment_notify_email' => '',
        // Then cleared by dls:blog:prune-comment-ips (scheduled daily).
        'comment_ip_retention_days' => '30',
        // Linked from the comment form when no plugin provides a privacy
        // policy.
        'privacy_policy_url' => '',
    ];

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    /**
     * The first path segment of the public blog URLs. A stored value that
     * is not a valid slug reads as the default, so a bad row cannot break
     * route registration.
     */
    public function routeSlug(): string
    {
        $slug = (string) $this->value('route_slug');

        return preg_match(self::ROUTE_SLUG_PATTERN, $slug) === 1 && strlen($slug) <= self::ROUTE_SLUG_MAX
            ? $slug
            : self::ROUTE_SLUG_DEFAULT;
    }

    /**
     * Posts per page on the index (cards or in full) and the archives.
     */
    public function postsPerPage(): int
    {
        $value = (int) $this->value('posts_per_page');

        return max(self::POSTS_PER_PAGE_MIN, min(self::POSTS_PER_PAGE_MAX, $value));
    }

    /**
     * How the top page shows posts: 'cards' or 'full'. An unknown stored
     * value reads as cards.
     */
    public function indexLayout(): string
    {
        $value = (string) $this->value('index_layout');

        return in_array($value, self::INDEX_LAYOUTS, true) ? $value : 'cards';
    }

    public function sidebarEnabled(): bool
    {
        return $this->value('sidebar_enabled') === '1';
    }

    public function sidebarPosition(): string
    {
        $position = (string) $this->value('sidebar_position');

        return in_array($position, self::SIDEBAR_POSITIONS, true) ? $position : self::SIDEBAR_POSITIONS[0];
    }

    /**
     * Every known widget once, in the stored order. Unknown keys are
     * dropped; a widget missing from the stored list (one added by a later
     * release) is appended, enabled.
     *
     * @return list<array{key: string, enabled: bool}>
     */
    public function sidebarWidgets(): array
    {
        $stored = json_decode((string) $this->value('sidebar_widgets'), true);
        $widgets = [];

        foreach (is_array($stored) ? $stored : [] as $item) {
            $key = is_array($item) ? ($item['key'] ?? null) : null;
            if (in_array($key, self::SIDEBAR_WIDGETS, true) && ! isset($widgets[$key])) {
                $widgets[$key] = ['key' => $key, 'enabled' => (bool) ($item['enabled'] ?? false)];
            }
        }
        foreach (self::SIDEBAR_WIDGETS as $key) {
            $widgets[$key] ??= ['key' => $key, 'enabled' => true];
        }

        return array_values($widgets);
    }

    public function sidebarRecentCount(): int
    {
        $value = (int) $this->value('sidebar_recent_count');

        return max(self::RECENT_POSTS_MIN, min(self::RECENT_POSTS_MAX, $value));
    }

    /**
     * The share buttons shown, in SHARE_SERVICES order.
     *
     * @return list<string>
     */
    public function shareServices(): array
    {
        $stored = json_decode((string) $this->value('share_services'), true);
        $stored = is_array($stored) ? $stored : [];

        return array_values(array_filter(self::SHARE_SERVICES, fn (string $key) => in_array($key, $stored, true)));
    }

    /**
     * Whether posts take comments unless a post says otherwise.
     */
    public function commentsEnabled(): bool
    {
        return $this->value('comments_enabled') === '1';
    }

    /**
     * Whether a new visitor comment waits for an admin's approval.
     */
    public function commentsNeedApproval(): bool
    {
        return $this->value('comment_moderation') !== 'immediate';
    }

    /**
     * Days after publishing that a post stops taking comments; 0 never.
     */
    public function commentsCloseAfterDays(): int
    {
        return max(0, min(self::COMMENTS_CLOSE_AFTER_DAYS_MAX, (int) $this->value('comments_close_after_days')));
    }

    /**
     * Links a comment may hold before it is held for approval.
     */
    public function commentLinkLimit(): int
    {
        return max(0, min(self::COMMENT_LINK_LIMIT_MAX, (int) $this->value('comment_link_limit')));
    }

    public function commentNotify(): bool
    {
        return $this->value('comment_notify') === '1';
    }

    /**
     * Where new comments are reported: the address set here, else the
     * site's system admin address. Empty when neither is set.
     */
    public function commentNotifyEmail(): string
    {
        $address = trim((string) $this->value('comment_notify_email'));

        return $address !== '' ? $address : trim(ConfigHelper::getNotificationEmail());
    }

    public function commentRateMaxAttempts(): int
    {
        return max(1, min(self::COMMENT_RATE_MAX_ATTEMPTS_MAX, (int) $this->value('comment_rate_max_attempts')));
    }

    public function commentRateDecayMinutes(): int
    {
        return max(1, min(self::COMMENT_RATE_DECAY_MINUTES_MAX, (int) $this->value('comment_rate_decay_minutes')));
    }

    public function commentIpRetentionDays(): int
    {
        return max(0, min(self::COMMENT_IP_RETENTION_DAYS_MAX, (int) $this->value('comment_ip_retention_days')));
    }

    public function privacyPolicyUrl(): ?string
    {
        $url = trim((string) $this->value('privacy_policy_url'));

        return $url !== '' ? $url : null;
    }

    /**
     * Store settings and forget the values read so far.
     *
     * @param  array<string, string|int|bool|array<mixed>|null>  $values
     */
    public function save(array $values): void
    {
        $known = array_intersect_key($values, self::DEFAULTS);
        BlogSetting::store(array_map(
            fn ($value) => match (true) {
                is_bool($value) => $value ? '1' : '0',
                is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES),
                $value === null => null,
                default => (string) $value,
            },
            $known,
        ));
        $this->values = null;
    }

    /**
     * Every setting as stored, defaults filled in.
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->values ??= BlogSetting::valuesFor(array_keys(self::DEFAULTS)) + self::DEFAULTS;
    }

    private function value(string $name): ?string
    {
        return $this->all()[$name] ?? self::DEFAULTS[$name] ?? null;
    }
}
