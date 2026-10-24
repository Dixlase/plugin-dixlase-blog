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
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;

/**
 * Keep a post's own CSS and JavaScript to the members and editor types
 * that may have them.
 *
 * Only members who may author scripts set either. From anyone else the
 * fields are dropped from the input, so the stored ones are left alone:
 * an editor saving a Markdown post keeps the CSS an admin wrote for it.
 * A form without the fields (Simple mode hides them) does the same.
 *
 * Otherwise the editor type decides: CSS for HTML and Markdown,
 * JavaScript for HTML alone. What a type does not carry is set to null,
 * so nothing it would not serve is stored.
 */
trait LimitsCustomAssets
{
    protected function limitCustomAssets(?ContentEditorType $editorType): void
    {
        if (! BlogScriptAuthoringPolicy::canAuthorScripts(Auth::guard('member')->user())) {
            $this->request->remove('custom_css');
            $this->request->remove('custom_js');

            return;
        }

        if (! BlogScriptAuthoringPolicy::carriesCustomCss($editorType)) {
            $this->merge(['custom_css' => null]);
        }
        if (! BlogScriptAuthoringPolicy::carriesCustomJs($editorType)) {
            $this->merge(['custom_js' => null]);
        }
    }
}
