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

use App\Helpers\DateTimeHelper;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The two sides of the publish / close datetime fields.
 *
 * Dixlase stores and compares datetimes in UTC and converts to
 * site_settings.display_timezone only for display (see
 * App\Helpers\DateTimeHelper). An <input type="datetime-local"> has no
 * offset, so the wall clock it submits means nothing until a timezone is
 * chosen for it: read in UTC, a post scheduled for 10:00 on a site
 * displaying Asia/Tokyo goes live at 19:00 local.
 *
 * Both directions live here so they cannot drift apart. A field shown in
 * one timezone and saved as another stops round-tripping, which is silent
 * — the form reads back what it wrote and only the published page
 * disagrees.
 *
 * Core gains a <x-form-datetime> component that does this generically
 * (dixlase-core#763); this class is what the plugin moves off when it
 * ships.
 */
final class DisplayTimezoneDatetime
{
    /**
     * The timezone the fields are shown and read in.
     */
    public static function zone(): string
    {
        return DateTimeHelper::displayTimezone();
    }

    /**
     * A stored UTC value as the wall clock a datetime-local field wants.
     *
     * Accepts what old() returns as readily as a model attribute: after a
     * failed validation the flashed value is the UTC string this class
     * produced, so the same call covers both.
     *
     * @param  \Carbon\CarbonImmutable|\DateTimeInterface|string|int|null  $value
     */
    public static function forInput(mixed $value): ?string
    {
        return DateTimeHelper::display($value, 'Y-m-d\TH:i');
    }

    /**
     * A submitted wall clock read in the display timezone, as UTC.
     *
     * Returns null both for an empty value and for one that cannot be
     * parsed. A caller normalising submitted input must tell the two apart
     * and leave an unparseable value exactly as it arrived, so its `date`
     * rule reports it instead of `nullable` dropping it in silence.
     */
    public static function toUtc(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            // An explicit offset in the submitted value wins: Carbon
            // honours it and ignores the second argument. Only a bare wall
            // clock, which is what datetime-local sends, is read as local
            // to the display timezone.
            return CarbonImmutable::parse($value, self::zone())
                ->setTimezone((string) config('app.timezone', 'UTC'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
