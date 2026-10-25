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

use App\Enums\ContentEditorType;
use Illuminate\Support\Facades\Auth;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;

/**
 * Keep a post's own CSS and JavaScript to the members and editor types
 * that may have them.
 *
 * Both fields are always set explicitly, so what reaches validation is
 * what this decides and never what happened to arrive:
 *
 *  - an editor type keeps only what it serves (CSS for HTML and Markdown,
 *    JavaScript for HTML alone), so nothing that would not be served is
 *    stored;
 *  - only members who may author scripts set either. From anyone else, and
 *    from a form without the field (Simple mode hides the tabs), the stored
 *    one is carried over, so an editor saving a Markdown post keeps the CSS
 *    an admin wrote for it. On a new post there is none to carry, so it
 *    stays empty.
 */
trait LimitsCustomAssets
{
    protected function limitCustomAssets(?ContentEditorType $editorType, ?BlogPost $post = null): void
    {
        $canAuthorScripts = BlogScriptAuthoringPolicy::canAuthorScripts(Auth::guard('member')->user());

        $carries = [
            'custom_css' => BlogScriptAuthoringPolicy::carriesCustomCss($editorType),
            'custom_js' => BlogScriptAuthoringPolicy::carriesCustomJs($editorType),
        ];

        $merge = [];
        foreach ($carries as $field => $carried) {
            $merge[$field] = match (true) {
                ! $carried => null,
                $canAuthorScripts && $this->has($field) => $this->input($field),
                default => $post?->getAttribute($field),
            };
        }

        $this->merge($merge);
    }
}
