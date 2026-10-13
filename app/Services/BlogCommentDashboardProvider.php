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

use App\Contracts\PluginIntegration\DashboardNotificationProviderInterface;
use App\DTO\PluginIntegration\DashboardNotificationDTO;
use Throwable;

/**
 * Tells the dashboard how many comments wait for approval. Core's admin
 * menu has no badges, so this is where the count shows.
 */
class BlogCommentDashboardProvider implements DashboardNotificationProviderInterface
{
    public function __construct(private readonly BlogComments $comments)
    {
    }

    public function getPluginSlug(): string
    {
        return 'dixlase-blog';
    }

    public function isCapabilityAvailable(): bool
    {
        return true;
    }

    public function getNotifications(): array
    {
        try {
            $pending = $this->comments->pendingCount();
        } catch (Throwable) {
            // The comments table is missing until the migrations run.
            return [];
        }

        if ($pending === 0) {
            return [];
        }

        return [
            new DashboardNotificationDTO(
                key: 'blog_comments_pending',
                level: 'info',
                message: trans_choice('dixlase-blog::admin.comments.dashboard_pending', $pending, ['count' => $pending]),
                icon: 'fas fa-comments',
                pluginName: __('dixlase-blog::admin.nav.blog.text'),
                url: route('dixlase-blog::admin.blog.comments.index', ['status' => 'pending']),
                actionLabel: __('dixlase-blog::admin.comments.dashboard_action'),
            ),
        ];
    }
}
