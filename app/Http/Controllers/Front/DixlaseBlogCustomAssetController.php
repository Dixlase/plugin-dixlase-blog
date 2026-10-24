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

namespace Plugins\DixlaseBlog\App\Http\Controllers\Front;

use App\Enums\ContentEditorType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;

/**
 * A post's own CSS and JavaScript, served as files next to it
 * (/blog/{slug}/custom-style.css and custom-script.js) so the post page
 * links them under the site's content security policy: a same-origin
 * file needs no nonce. Only for a post the public pages show now, and
 * only for an editor type that keeps the file: CSS for HTML and Markdown,
 * JavaScript for HTML alone (BlogScriptAuthoringPolicy). Anything else is
 * the same 404 as a missing post. The editors' previews do not use these
 * routes; they put the CSS in the page and leave the JavaScript out.
 */
class DixlaseBlogCustomAssetController extends Controller
{
    public function style(Request $request): Response
    {
        return $this->asset($request, 'custom_css', 'text/css', BlogScriptAuthoringPolicy::carriesCustomCss(...));
    }

    public function script(Request $request): Response
    {
        return $this->asset($request, 'custom_js', 'application/javascript', BlogScriptAuthoringPolicy::carriesCustomJs(...));
    }

    /**
     * The slug is read by name; see DixlaseBlogFrontController::show().
     *
     * @param  callable(?ContentEditorType): bool  $carries  whether the post's editor type keeps this file
     */
    private function asset(Request $request, string $column, string $contentType, callable $carries): Response
    {
        $post = BlogPost::query()
            ->currentlyVisible()
            ->where('slug', (string) $request->route('slug'))
            ->first();

        $content = $post !== null && $carries($post->editor_type)
            ? (string) $post->getAttribute($column)
            : '';
        abort_if(trim($content) === '', 404);

        return response($content, 200, [
            'Content-Type' => $contentType.'; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.md5($content).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
