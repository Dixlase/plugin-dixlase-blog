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

namespace Plugins\DixlaseBlog\App\Http\Requests\Admin\Concerns;

/**
 * Turn the "nothing chosen" values an admin form submits into null.
 *
 * Core does not convert empty strings to null, so an empty <input> or a
 * <select> left on its placeholder arrives as "". A `nullable` rule lets
 * that through unchanged, and MySQL then refuses "" in an integer or
 * datetime column (SQLSTATE 22007). SQLite accepts it, which is why only a
 * MySQL site shows the error.
 */
trait NullsBlankInput
{
    /**
     * @param  list<string>  $fields  set to null when ""
     * @param  list<string>  $ids  set to null when "", "0" or 0 (media pickers
     *                             and parent selects use 0 for "none")
     */
    protected function nullBlankInput(array $fields, array $ids = []): void
    {
        $merge = [];

        foreach ($fields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        foreach ($ids as $field) {
            if ($this->has($field) && in_array($this->input($field), ['', '0', 0], true)) {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
