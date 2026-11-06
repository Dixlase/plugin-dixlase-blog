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

use App\Models\Member;
use App\Services\Media\MediaImporter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostUpdateRequest;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Support\DisplayTimezoneDatetime;
use Plugins\DixlaseBlog\App\Support\Import\FrontMatter;
use Plugins\DixlaseBlog\App\Support\Import\FrontMatterException;
use Plugins\DixlaseBlog\App\Support\Import\PostImportException;

/**
 * Create or update a post from a Markdown file with front matter (#210)
 *
 * The command-line counterpart of saving the editor. The file is checked
 * with the editor's own form requests and saved through
 * {@see BlogPostService} and {@see BlogSeoMeta}, so revisions, file storage,
 * the audit trail and the script-authoring policy behave as they do in the
 * admin. A post whose slug already exists is updated, not duplicated, and a
 * field the front matter leaves out keeps its current value.
 *
 * Local images named in `featured_image`, `seo.ogp_image` and the body's
 * `![alt](path)` are imported through core's {@see MediaImporter} and
 * replaced by the media item. Nothing is imported until the file has passed
 * validation once, so a broken file adds nothing to the media library.
 */
class BlogPostImporter
{
    /** Front matter keys a post file may use */
    public const KEYS = [
        'title', 'slug', 'lang', 'status', 'published_at', 'closed_at',
        'categories', 'tags', 'excerpt', 'featured_image', 'is_pinned',
        'comment_status', 'seo',
    ];

    public const SEO_KEYS = ['description', 'ogp_image'];

    /** `![alt](path)` or `![alt](path "title")`; group 2 is the path */
    private const BODY_IMAGE = '/!\[([^\]]*)\]\(\s*<?([^\s)>]+)>?((?:\s+"[^"]*")?)\s*\)/u';

    public function __construct(
        private readonly BlogPostService $posts,
        private readonly BlogSeoMeta $seoMeta,
        private readonly MediaImporter $media,
    ) {}

    /**
     * @return array{action: 'create'|'update', post: ?BlogPost, images: list<array{source: string, field: string, media_id: ?int, path: ?string, reused: bool}>}
     *
     * @throws PostImportException
     */
    public function import(string $file, Member $author, bool $dryRun = false): array
    {
        $markdown = $this->read($file);

        try {
            $frontMatter = FrontMatter::parse($markdown);
        } catch (FrontMatterException $e) {
            throw new PostImportException([$e->getMessage()]);
        }

        $fields = $frontMatter->fields;
        $this->checkKeys($fields);

        $post = $this->existingPost($fields['slug'] ?? null);
        if ($post !== null && $post->editor_type->slug() !== 'markdown') {
            throw new PostImportException([__('dixlase-blog::import.not_markdown', ['slug' => $post->slug])]);
        }
        $images = $this->localImages($fields, $frontMatter->body, dirname((string) realpath($file)));
        $data = $this->data($fields, $frontMatter->body, $post);

        // Once without the images, so a file that cannot be saved imports
        // nothing into the media library.
        $this->validate($data, $post, $author);

        $plan = [
            'action' => $post === null ? 'create' : 'update',
            'post' => $post,
            'images' => array_map(fn (array $image): array => $image + ['media_id' => null, 'path' => null, 'reused' => false], $images),
        ];

        if ($dryRun) {
            return $plan;
        }

        $plan['images'] = $this->importImages($images, $author);
        $data = $this->withImages($data, $plan['images'], $fields);
        $validated = $this->validate($data, $post, $author);

        $saved = DB::transaction(function () use ($post, $validated, $author): BlogPost {
            $saved = $post === null
                ? $this->posts->create($validated, $author->id)
                : $this->posts->update($post, $validated, $author->id);
            $this->seoMeta->save($saved, $validated);

            return $saved;
        });

        $plan['post'] = $saved;

        return $plan;
    }

    /**
     * @throws PostImportException
     */
    private function read(string $file): string
    {
        $real = realpath($file);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new PostImportException([__('dixlase-blog::import.file_not_found', ['path' => $file])]);
        }

        return (string) file_get_contents($real);
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws PostImportException
     */
    private function checkKeys(array $fields): void
    {
        $errors = [];

        foreach (array_keys($fields) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                $errors[] = __('dixlase-blog::import.unknown_key', ['key' => $key, 'keys' => implode(', ', self::KEYS)]);
            }
        }

        if (isset($fields['seo'])) {
            if (! is_array($fields['seo']) || array_is_list($fields['seo'])) {
                $errors[] = __('dixlase-blog::import.seo_not_a_map', ['keys' => implode(', ', self::SEO_KEYS)]);
            } else {
                foreach (array_keys($fields['seo']) as $key) {
                    if (! in_array($key, self::SEO_KEYS, true)) {
                        $errors[] = __('dixlase-blog::import.unknown_key', ['key' => 'seo.'.$key, 'keys' => implode(', ', self::SEO_KEYS)]);
                    }
                }
            }
        }

        foreach (['title', 'slug', 'lang', 'status', 'published_at', 'closed_at', 'excerpt', 'featured_image', 'comment_status'] as $key) {
            if (isset($fields[$key]) && ! is_string($fields[$key])) {
                $errors[] = __('dixlase-blog::import.not_text', ['key' => $key]);
            }
        }

        if ($errors !== []) {
            throw new PostImportException($errors);
        }
    }

    private function existingPost(mixed $slug): ?BlogPost
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return BlogPost::query()->where('slug', $slug)->first();
    }

    /**
     * The local files the front matter and the body point at
     *
     * A path is local when it is relative: a URL (`https:`, `data:`) or a
     * root-relative site path (`/storage/…`) is left as it is.
     *
     * @param  array<string, mixed>  $fields
     * @return list<array{source: string, field: string, file: string}>
     *
     * @throws PostImportException
     */
    private function localImages(array $fields, string $body, string $baseDir): array
    {
        $refs = [];
        if (is_string($fields['featured_image'] ?? null)) {
            $refs[] = ['featured_image', $fields['featured_image']];
        }
        if (is_string($fields['seo']['ogp_image'] ?? null)) {
            $refs[] = ['seo.ogp_image', $fields['seo']['ogp_image']];
        }
        preg_match_all(self::BODY_IMAGE, $body, $matches);
        foreach ($matches[2] as $path) {
            $refs[] = ['body', $path];
        }

        $images = [];
        $errors = [];
        foreach ($refs as [$field, $path]) {
            if (! self::isLocal($path)) {
                continue;
            }

            $file = realpath($baseDir.DIRECTORY_SEPARATOR.rawurldecode($path));
            if ($file === false || ! is_file($file)) {
                $errors[] = __('dixlase-blog::import.image_not_found', ['field' => $field, 'path' => $path]);

                continue;
            }

            $images[] = ['source' => $path, 'field' => $field, 'file' => $file];
        }

        if ($errors !== []) {
            throw new PostImportException($errors);
        }

        return $images;
    }

    public static function isLocal(string $path): bool
    {
        return $path !== ''
            && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) !== 1
            && ! str_starts_with($path, '/')
            && ! str_starts_with($path, '#');
    }

    /**
     * The editor's form input, before the images are known
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     *
     * @throws PostImportException
     */
    private function data(array $fields, string $body, ?BlogPost $post): array
    {
        $data = [
            'title' => $fields['title'] ?? $post?->title,
            'slug' => $fields['slug'] ?? null,
            'content' => $body,
            'editor_type' => $post?->editor_type->slug() ?? 'markdown',
            'status' => $fields['status'] ?? $post?->status->slug() ?? 'draft',
        ];

        foreach (['lang', 'excerpt', 'comment_status'] as $key) {
            if (array_key_exists($key, $fields)) {
                $data[$key] = $fields[$key];
            }
        }
        if ($post !== null && ! array_key_exists('excerpt', $fields)) {
            $data['excerpt'] = $post->excerpt;
        }
        if (array_key_exists('lang', $fields) && $post !== null) {
            // Naming the language in the file is the confirmation the
            // editor asks for when it changes.
            $data['confirm_lang_change'] = true;
        }
        if (array_key_exists('is_pinned', $fields)) {
            $data['is_pinned'] = (bool) $fields['is_pinned'];
        }

        foreach (['published_at', 'closed_at'] as $key) {
            if (array_key_exists($key, $fields)) {
                $data[$key] = $fields[$key];
            } elseif ($post?->{$key} !== null) {
                $data[$key] = DisplayTimezoneDatetime::forInput($post->{$key});
            }
        }

        if (array_key_exists('categories', $fields)) {
            $data['categories'] = $this->categoryIds((array) ($fields['categories'] ?? []));
        }
        if (array_key_exists('tags', $fields)) {
            $data['tags'] = array_values(array_map('strval', array_filter((array) ($fields['tags'] ?? []), fn ($tag) => $tag !== null && $tag !== '')));
        }

        // The images are filled in after validation; until then a local
        // path keeps the current one, and a site URL is not accepted.
        if (array_key_exists('featured_image', $fields)) {
            $data['featured_media_id'] = $fields['featured_image'] === null ? null : $post?->featured_media_id;
        } elseif ($post !== null) {
            $data['featured_media_id'] = $post->featured_media_id;
        }

        if (array_key_exists('seo', $fields)) {
            $seo = (array) ($fields['seo'] ?? []);
            $data['seo_meta'] = [];
            if (array_key_exists('description', $seo)) {
                $data['seo_meta']['description'] = $seo['description'];
            }
            if (array_key_exists('ogp_image', $seo)) {
                $data['seo_meta']['ogp_media_id'] = null;
            }
        }

        foreach (['featured_image' => $fields['featured_image'] ?? null, 'seo.ogp_image' => $fields['seo']['ogp_image'] ?? null] as $field => $value) {
            if (is_string($value) && ! self::isLocal($value)) {
                throw new PostImportException([__('dixlase-blog::import.image_must_be_local', ['field' => $field])]);
            }
        }

        return $data;
    }

    /**
     * @param  array<int, mixed>  $slugs
     * @return list<int>
     *
     * @throws PostImportException
     */
    private function categoryIds(array $slugs): array
    {
        $slugs = array_values(array_filter(array_map(fn ($slug) => is_string($slug) ? $slug : '', $slugs)));
        $ids = BlogCategory::query()->whereIn('slug', $slugs)->pluck('id', 'slug')->all();

        $unknown = array_diff($slugs, array_keys($ids));
        if ($unknown !== []) {
            throw new PostImportException([__('dixlase-blog::import.unknown_categories', ['slugs' => implode(', ', $unknown)])]);
        }

        return array_values(array_map('intval', array_map(fn (string $slug) => $ids[$slug], $slugs)));
    }

    /**
     * @param  list<array{source: string, field: string, file: string}>  $images
     * @return list<array{source: string, field: string, media_id: ?int, path: ?string, reused: bool}>
     *
     * @throws PostImportException
     */
    private function importImages(array $images, Member $author): array
    {
        $done = [];
        $results = [];
        $errors = [];

        foreach ($images as $image) {
            $result = $done[$image['file']] ??= $this->media->import($image['file'], $author);

            if ($result->media === null) {
                $errors[] = __('dixlase-blog::import.image_failed', ['field' => $image['field'], 'path' => $image['source'], 'error' => (string) $result->error]);

                continue;
            }

            $results[] = [
                'source' => $image['source'],
                'field' => $image['field'],
                'media_id' => (int) $result->media->id,
                'path' => MediaImporter::pathOf($result->media),
                'reused' => $result->reused,
            ];
        }

        if ($errors !== []) {
            throw new PostImportException($errors);
        }

        return $results;
    }

    /**
     * Put the imported media into the form input
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{source: string, field: string, media_id: ?int, path: ?string, reused: bool}>  $images
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function withImages(array $data, array $images, array $fields): array
    {
        $paths = [];
        foreach ($images as $image) {
            match ($image['field']) {
                'featured_image' => $data['featured_media_id'] = $image['media_id'],
                'seo.ogp_image' => $data['seo_meta']['ogp_media_id'] = $image['media_id'],
                default => $paths[$image['source']] = $image['path'],
            };
        }

        if ($paths !== []) {
            $data['content'] = preg_replace_callback(
                self::BODY_IMAGE,
                fn (array $m): string => isset($paths[$m[2]])
                    ? '!['.$m[1].']('.$paths[$m[2]].$m[3].')'
                    : $m[0],
                (string) $data['content'],
            ) ?? $data['content'];
        }

        return $data;
    }

    /**
     * Run the editor's form request on the input, as the author
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws PostImportException
     */
    private function validate(array $data, ?BlogPost $post, Member $author): array
    {
        /** @var class-string<FormRequest> $class */
        $class = $post === null ? BlogPostStoreRequest::class : BlogPostUpdateRequest::class;

        $request = $class::create('/', $post === null ? 'POST' : 'PUT', $data);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $author);

        if ($post !== null) {
            $route = new Route('PUT', 'blog/{post}', []);
            $route->bind($request);
            $route->setParameter('post', (string) $post->id);
            $request->setRouteResolver(fn () => $route);
        }

        $guard = Auth::guard('member');
        $previous = $guard->user();
        $guard->setUser($author);

        try {
            $request->validateResolved();

            return $request->validated();
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = $field.': '.$message;
                }
            }

            throw new PostImportException($errors);
        } finally {
            if ($previous !== null) {
                $guard->setUser($previous);
            } else {
                $guard->forgetUser();
            }
        }
    }
}
