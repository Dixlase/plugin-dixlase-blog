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
use Illuminate\Support\Facades\Storage;
use Plugins\DixlaseBlog\App\Models\BlogPost;

/**
 * The files of a post stored as files (#193)
 *
 * Each post has a directory of its own, named by its id so that a renamed
 * slug or a post in the trash never moves or shares one:
 *
 *   storage/app/private/dixlase-blog/posts/{id}/content.{md,html,json}
 *   storage/app/private/dixlase-blog/posts/{id}/style.css
 *   storage/app/private/dixlase-blog/posts/{id}/script.js
 *
 * The same names as core's front page, which the content editor shows. The
 * body is in the post's own language; translations stay where the
 * translation editor keeps them.
 */
final class BlogPostFiles
{
    public const DISK = 'local';

    private const EXTENSIONS = [
        'gui' => 'json',
        'markdown' => 'md',
        'html' => 'html',
    ];

    /**
     * The directory of a post's files on the disk
     */
    public function directory(BlogPost $post): string
    {
        return self::baseDirectory().'/'.$post->getKey();
    }

    /**
     * The directory that holds every post's directory, on the disk
     */
    public static function baseDirectory(): string
    {
        return BlogPost::PLUGIN_SLUG.'/posts';
    }

    /**
     * The path of a part's file on the disk: content, custom_css or custom_js
     */
    public function path(BlogPost $post, string $part): string
    {
        return $this->directory($post).'/'.match ($part) {
            'custom_css' => 'style.css',
            'custom_js' => 'script.js',
            default => 'content.'.$this->extension($post->editor_type),
        };
    }

    /**
     * Write the parts the post keeps; an empty part has no file
     *
     * @param  array<string, string|null>  $values  by part
     */
    public function write(BlogPost $post, array $values): void
    {
        $disk = Storage::disk(self::DISK);

        foreach (array_keys($post->fileContentParts()) as $part) {
            $value = (string) ($values[$part] ?? '');
            $path = $this->path($post, $part);

            if ($value === '') {
                $disk->delete($path);
            } else {
                $disk->put($path, $value);
            }
        }
    }

    /**
     * Remove every file of a post
     */
    public function delete(BlogPost $post): void
    {
        Storage::disk(self::DISK)->deleteDirectory($this->directory($post));
    }

    private function extension(?ContentEditorType $editorType): string
    {
        return self::EXTENSIONS[$editorType?->slug() ?? ''] ?? 'txt';
    }
}
