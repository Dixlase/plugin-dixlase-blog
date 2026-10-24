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

use Plugins\DixlaseBlog\App\Support\DisplayTimezoneDatetime;

/**
 * Read datetime-local fields as wall clocks in the site's display timezone
 * and merge them back as UTC. See DisplayTimezoneDatetime for why.
 *
 * The conversion belongs in prepareForValidation() so the rules compare
 * normalized values — closed_at's `after:published_at` included. What is
 * flashed on a failed validation is therefore the UTC value too, which is
 * why the view renders old() through DisplayTimezoneDatetime::forInput()
 * rather than printing it raw.
 */
trait ReadsDatetimeInDisplayTimezone
{
    /**
     * @param  list<string>  $fields  datetime-local fields to read in the display timezone
     */
    protected function readDatetimeInDisplayTimezone(array $fields): void
    {
        $merge = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $utc = DisplayTimezoneDatetime::toUtc($value);

            // An unparseable value is left exactly as submitted, so the
            // field's `date` rule reports it. Merging the null would pass
            // `nullable` instead and drop what the member typed without
            // saying so.
            if ($utc !== null) {
                $merge[$field] = $utc;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
