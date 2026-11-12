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
use App\Helpers\DateTimeHelper;
use App\Services\Content\FileContentPublisher;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Support\Import\PostImportException;

/**
 * Write a post as the Markdown file the import reads (#235)
 *
 * The way back from {@see BlogPostImporter}: a post edited in the admin can
 * be continued in a file, and importing that file again leaves the post as
 * it is. The front matter carries `revision`, the post's version when the
 * file was taken.
 *
 * Images are left out of the front matter: a key the import does not see
 * keeps its current value, so the featured and OGP images stay. Images in
 * the body keep their site paths, which the import leaves alone.
 *
 * Markdown and HTML posts are exported, with `editor` naming which (#251).
 * The post's own CSS and JavaScript are returned as files to write next to
 * the post file, named by `custom_css` / `custom_js`; without a name for
 * them they are left out, and the import keeps the post's current ones.
 */
class BlogPostExporter
{
    public function __construct(
        private readonly BlogPostService $posts,
        private readonly BlogSeoMeta $seoMeta,
    ) {}

    /**
     * The file, and the CSS / JavaScript files to write next to it
     *
     * `assets` maps a file name to its contents; `omitted` lists the keys
     * left out because no name was given for their files.
     *
     * @param  ?string  $assetName  The name, without extension, of the CSS /
     *                              JavaScript files; null leaves them out
     * @return array{markdown: string, assets: array<string, string>, omitted: list<string>}
     *
     * @throws PostImportException
     */
    public function export(BlogPost $post, ?string $assetName = null): array
    {
        if (! in_array($post->editor_type, [ContentEditorType::MARKDOWN, ContentEditorType::HTML], true)) {
            throw new PostImportException([__('dixlase-blog::export.editor_not_exportable', ['slug' => $post->slug, 'editor' => $post->editor_type->slug()])]);
        }

        $parts = $this->parts($post);

        $post->loadMissing(['categories', 'tags']);

        $lines = [
            'title: '.$this->quote((string) $post->title),
            'slug: '.$this->quote((string) $post->slug),
            'lang: '.$this->quote((string) $post->lang),
            'status: '.$post->status->slug(),
            'editor: '.$post->editor_type->slug(),
        ];
        foreach (['published_at', 'closed_at'] as $key) {
            $value = DateTimeHelper::display($post->{$key}, 'Y-m-d H:i:s');
            if ($value !== null) {
                $lines[] = $key.': '.$this->quote($value);
            }
        }
        $lines[] = 'categories: '.$this->list($post->categories->pluck('slug')->all());
        $lines[] = 'tags: '.$this->list($post->tags->pluck('name')->all());
        if (filled($post->excerpt)) {
            $lines[] = 'excerpt: '.$this->quote((string) $post->excerpt);
        }
        $lines[] = 'is_pinned: '.($post->is_pinned ? 'true' : 'false');
        $lines[] = 'comment_status: '.$post->comment_status;

        $description = $this->seoMeta->formData($post)['seoMeta']?->description;
        if (filled($description)) {
            $lines[] = 'seo:';
            $lines[] = '  description: '.$this->quote((string) $description);
        }

        $assets = [];
        $omitted = [];
        foreach (['custom_css' => 'css', 'custom_js' => 'js'] as $key => $extension) {
            $value = $parts[$key] ?? '';
            if ($value === '') {
                continue;
            }
            if ($assetName === null) {
                $omitted[] = $key;

                continue;
            }

            $name = $assetName.'.'.$extension;
            $assets[$name] = $value;
            $lines[] = $key.': '.$this->quote('./'.$name);
        }

        $lines[] = 'revision: '.$this->posts->versionToken($post);

        return [
            'markdown' => "---\n".implode("\n", $lines)."\n---\n\n".($parts['content'] ?? ''),
            'assets' => $assets,
            'omitted' => $omitted,
        ];
    }

    /**
     * The body, CSS and JavaScript the editor shows: a post stored as files
     * is edited from its working copy, which may hold a change not
     * published yet. Written as stored, so importing them again changes
     * nothing.
     *
     * @return array<string, string>
     */
    private function parts(BlogPost $post): array
    {
        if ($post->usesFileStorage()) {
            return app(FileContentPublisher::class)->readFiles($post);
        }

        $parts = ['content' => (string) $post->content];
        foreach (array_keys($post->fileContentParts()) as $part) {
            $parts[$part] = (string) $post->getAttribute($part);
        }

        return $parts;
    }

    /**
     * A double-quoted value the front matter reads back unchanged
     */
    private function quote(string $value): string
    {
        return '"'.addcslashes($value, "\\\"\n\r\t").'"';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function list(array $values): string
    {
        return '['.implode(', ', array_map(fn ($value): string => $this->quote((string) $value), $values)).']';
    }
}
