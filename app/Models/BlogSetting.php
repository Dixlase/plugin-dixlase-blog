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

namespace Plugins\DixlaseBlog\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * One blog setting, stored as a name / value row. Read settings through
 * BlogSettings, which knows the defaults and the types.
 *
 * @property string $name
 * @property string|null $value
 */
class BlogSetting extends Model
{
    protected $table = 'plg_dixlase_blog_settings';

    protected $fillable = ['name', 'value'];

    /**
     * Stored values by name, for the names given; a name without a row is
     * left out. Reads nothing while the table is missing (right after an
     * install whose migration failed, or mid-rollback): the front pages
     * read settings on every request and must not fail because of it. As
     * in DixlaseSEO, the probe is not memoised, so it cannot go stale.
     *
     * @param  list<string>  $names
     * @return array<string, string|null>
     */
    public static function valuesFor(array $names): array
    {
        try {
            if (! Schema::hasTable((new self())->getTable())) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        return self::query()->whereIn('name', $names)->pluck('value', 'name')->all();
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function store(array $values): void
    {
        foreach ($values as $name => $value) {
            self::query()->updateOrCreate(['name' => $name], ['value' => $value]);
        }
    }
}
