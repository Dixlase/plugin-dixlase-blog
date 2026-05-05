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
 */

declare(strict_types=1);

namespace Plugins\DixlaseBlog\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Plugin entry-point seeder. The Dixlase plugin install flow invokes
 * this class by convention; running it manually is also supported via:
 *   php artisan db:seed --class='Plugins\DixlaseBlog\Database\Seeders\DatabaseSeeder'
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BlogPostSeeder::class,
        ]);
    }
}
