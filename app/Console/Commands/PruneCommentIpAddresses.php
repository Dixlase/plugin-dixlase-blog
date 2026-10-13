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

namespace Plugins\DixlaseBlog\App\Console\Commands;

use Illuminate\Console\Command;
use Plugins\DixlaseBlog\App\Services\BlogComments;

/**
 * Clear commenters' IP addresses once they are older than the blog's
 * retention period. Scheduled daily by DixlaseBlogServiceProvider.
 */
class PruneCommentIpAddresses extends Command
{
    protected $signature = 'dls:blog:prune-comment-ips';

    protected $description = 'Clear blog commenters\' IP addresses older than the retention period';

    public function handle(BlogComments $comments): int
    {
        $count = $comments->pruneIpAddresses();
        $this->info("Cleared the IP address of {$count} comment(s).");

        return self::SUCCESS;
    }
}
