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

use App\Enums\ContentStorageType;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

/**
 * Where a post keeps its body, CSS and JavaScript (#193).
 *
 * File storage is offered only in the advanced admin mode, as in
 * DixlasePages. In Simple mode the form has no choice: a new post goes to
 * the database and a saved one stays where it is. Otherwise a form without
 * the field keeps the saved post's storage, or gives a new post the blog's
 * default.
 */
trait ChoosesStorageType
{
    protected function chooseStorageType(?BlogPost $post = null): void
    {
        $current = $post?->storage_type?->slug();

        $storageType = match (true) {
            BlogScriptAuthoringPolicy::isSimpleMode() => $current ?? ContentStorageType::DATABASE->slug(),
            $this->filled('storage_type') => $this->input('storage_type'),
            default => $current ?? app(BlogSettings::class)->defaultStorageType()->slug(),
        };

        $this->merge(['storage_type' => $storageType]);
    }
}
