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

namespace Plugins\DixlaseBlog\App\Services;

use App\Enums\ContentEditorType;
use App\Services\ContentPreviewService;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * Turns a post body into the HTML the front and the admin preview show.
 *
 * - markdown: core's renderer with raw HTML escaped and unsafe links
 *   dropped, as DixlasePages renders Markdown
 * - html: output as written; only members BlogScriptAuthoringPolicy allows
 *   may author or change it
 * - anything else (GUI JSON, legacy Blade): escaped plain text
 */
final class BlogContentRenderer
{
    public function __construct(private readonly ContentPreviewService $previewService) {}

    public function render(?string $content, ?ContentEditorType $editorType): string
    {
        $content ??= '';

        return match ($editorType) {
            ContentEditorType::MARKDOWN => $this->previewService->render($content, ContentEditorType::MARKDOWN, false),
            ContentEditorType::HTML => $content,
            default => '<pre>'.e($content).'</pre>',
        };
    }

    /**
     * The post's body in the current locale (the source text when there is
     * no translation), rendered. A post stored as files has its files
     * checked first, so the published copy is up to date (#193).
     */
    public function renderPost(BlogPost $post): string
    {
        $post->checkFiles();

        // A published translation wins; otherwise core's resolver decides
        // between the working copy on disk and the published column (#197).
        $body = $post->translationFor('content') ?? $post->resolvedPart('content');

        return $this->render($body, $post->editor_type);
    }
}
