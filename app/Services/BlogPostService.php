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
use App\Enums\ContentStatus;
use App\Helpers\LocaleHelper;
use App\Services\RevisionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Support\BlogExcerpt;

/**
 * Coordinates blog-post writes so the controller stays thin.
 *
 * Responsibilities:
 *   - Auto-generate slug / excerpt when blank
 *   - Cast string slugs ("draft", "markdown") to ContentStatus / ContentEditorType
 *     so the model's enum casts accept the value
 *   - Sync the multi-category and multi-tag pivots, upserting tags by name
 *     so the "#tag space-confirmed" admin flow can submit free-text labels
 *   - Record revisions with the column snapshot augmented by category_ids
 *     and tag_ids so a pivot-only edit (e.g. retag) is preserved in history
 */
class BlogPostService
{
    public function __construct(private readonly RevisionService $revisionService)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $authorId): BlogPost
    {
        return DB::transaction(function () use ($data, $authorId): BlogPost {
            $payload = $this->preparePayload($data);
            $payload['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['title'] ?? '', null);
            $payload['excerpt'] = $this->resolveExcerpt($data['excerpt'] ?? null, $payload['content'] ?? null);
            $payload['user_id'] = $authorId;
            $payload['lang'] = filled($data['lang'] ?? null) ? $data['lang'] : LocaleHelper::getSiteDefaultLocale();

            $post = BlogPost::create($payload);

            $this->syncCategories($post, $data['categories'] ?? []);
            $this->syncTagsByNames($post, $data['tags'] ?? []);

            $this->recordRevisionWithPivots($post, BlogPostRevision::TYPE_MANUAL, $authorId, null);

            return $post->fresh(['categories', 'tags']) ?? $post;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BlogPost $post, array $data, ?int $editorId): BlogPost
    {
        return DB::transaction(function () use ($post, $data, $editorId): BlogPost {
            $payload = $this->preparePayload($data);
            $payload['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['title'] ?? $post->title, $post->id);
            $payload['excerpt'] = $this->resolveExcerpt(
                $data['excerpt'] ?? null,
                $payload['content'] ?? $post->content,
            );
            if (array_key_exists('lang', $data)) {
                $payload['lang'] = $data['lang'] ?: $post->lang;
            }

            $post->update($payload);

            if (array_key_exists('categories', $data)) {
                $this->syncCategories($post, $data['categories'] ?? []);
            }
            if (array_key_exists('tags', $data)) {
                $this->syncTagsByNames($post, $data['tags'] ?? []);
            }

            $this->recordRevisionWithPivots($post, BlogPostRevision::TYPE_MANUAL, $editorId, null);

            return $post->fresh(['categories', 'tags']) ?? $post;
        });
    }

    /**
     * Bring a post back from the trash. A trashed post does not hold its
     * slug, so another post may have taken it meanwhile; the restored post
     * then moves to the next free one rather than sharing a live URL.
     */
    public function restoreFromTrash(BlogPost $post): BlogPost
    {
        return DB::transaction(function () use ($post): BlogPost {
            $post->slug = $this->resolveSlug($post->slug, (string) $post->title, $post->id);
            $post->restore();

            return $post;
        });
    }

    /**
     * Restore a post from a revision snapshot. Mirrors RevisionService::restore()
     * but extends it to re-sync the category_ids / tag_ids stored alongside the
     * column snapshot.
     */
    public function restoreRevision(BlogPostRevision $revision, ?int $userId): BlogPost
    {
        /** @var BlogPost $post */
        $post = BlogPost::withTrashed()->findOrFail($revision->post_id);
        /** @var array<string, mixed> $snapshot */
        $snapshot = $revision->snapshot ?? [];

        return DB::transaction(function () use ($post, $snapshot, $userId): BlogPost {
            $this->recordRevisionWithPivots($post, BlogPostRevision::TYPE_RESTORE_BACKUP, $userId, null);

            $columnData = array_intersect_key($snapshot, array_flip($post->revisionableFields()));
            // The snapshot's slug may belong to another post by now.
            if (array_key_exists('slug', $columnData)) {
                $columnData['slug'] = $this->resolveSlug((string) $columnData['slug'], (string) $post->title, $post->id);
            }
            $post->update($columnData);

            if (array_key_exists('category_ids', $snapshot) && is_array($snapshot['category_ids'])) {
                $this->syncCategories($post, $snapshot['category_ids']);
            }
            if (array_key_exists('tag_ids', $snapshot) && is_array($snapshot['tag_ids'])) {
                $post->tags()->sync($snapshot['tag_ids']);
            }

            return $post->fresh(['categories', 'tags']) ?? $post;
        });
    }

    /**
     * Convert string slugs to enum instances and drop fields the model does
     * not own, so BlogPost::create / update receives only valid attributes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data): array
    {
        $payload = collect($data)
            ->only([
                'title',
                'content',
                'custom_css',
                'custom_js',
                'featured_media_id',
                'editor_type',
                'status',
                'is_pinned',
                'comment_status',
                'lang',
                'published_at',
                'closed_at',
            ])
            ->all();

        if (isset($payload['editor_type']) && is_string($payload['editor_type'])) {
            $payload['editor_type'] = ContentEditorType::tryFromSlug($payload['editor_type']) ?? ContentEditorType::GUI;
        }
        if (isset($payload['status']) && is_string($payload['status'])) {
            $payload['status'] = ContentStatus::tryFromSlug($payload['status']) ?? ContentStatus::DRAFT;
        }

        // Auto-stamp published_at when transitioning to PUBLISHED without an explicit timestamp.
        if (
            ($payload['status'] ?? null) === ContentStatus::PUBLISHED
            && empty($payload['published_at'])
        ) {
            $payload['published_at'] = now();
        }

        return $payload;
    }

    private function resolveSlug(?string $candidate, string $title, ?int $excludeId): string
    {
        $base = trim((string) $candidate);
        if ($base === '') {
            $base = Str::slug($title);
            if ($base === '') {
                $base = 'post-'.substr(md5($title.microtime(true)), 0, 8);
            }
        }

        $slug = $base;
        $suffix = 2;
        while (
            BlogPost::query()
                ->where('slug', $slug)
                ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function resolveExcerpt(?string $candidate, ?string $content): ?string
    {
        return filled($candidate) ? $candidate : BlogExcerpt::fromContent($content);
    }

    /**
     * @param  array<int, int|string>  $categoryIds
     */
    private function syncCategories(BlogPost $post, array $categoryIds): void
    {
        $valid = BlogCategory::query()
            ->whereIn('id', array_map('intval', array_filter($categoryIds)))
            ->pluck('id')
            ->all();
        $post->categories()->sync($valid);
    }

    /**
     * Upsert tags by name (so the admin can type free-text labels with the
     * "#tag space-confirmed" input flow) and sync the post-tag pivot.
     *
     * @param  array<int, string>  $tagNames
     */
    private function syncTagsByNames(BlogPost $post, array $tagNames): void
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $slug = Str::slug($name);
            if ($slug === '') {
                $slug = 'tag-'.substr(md5($name), 0, 8);
            }
            // An existing tag is found by its name first: its slug need not
            // derive from the name (the sample tag's does not), and the
            // name is unique too. A new tag's name is in the language of
            // the post it is first used on.
            $tag = BlogTag::query()->where('name', $name)->first()
                ?? BlogTag::firstOrCreate(['slug' => $slug], ['name' => $name, 'lang' => $post->sourceLocale()]);
            $tagIds[] = $tag->id;
        }
        $post->tags()->sync(array_values(array_unique($tagIds)));
    }

    /**
     * Record a revision whose snapshot includes both column data and the
     * post's current category / tag pivot ids. Mirrors RevisionService::record()
     * but uses an augmented snapshot so the matchesLatest hash check covers
     * pivot-only edits (e.g. retagging without changing the body).
     */
    private function recordRevisionWithPivots(
        BlogPost $post,
        string $type,
        ?int $userId,
        ?string $note,
    ): ?BlogPostRevision {
        $retention = $this->revisionService->getRetentionCount();
        if ($retention === 0) {
            return null;
        }

        $snapshot = $this->buildSnapshotWithPivots($post);

        $latest = BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        if ($latest && $this->snapshotHash((array) ($latest->snapshot ?? [])) === $this->snapshotHash($snapshot)) {
            return null;
        }

        return DB::transaction(function () use ($post, $snapshot, $type, $userId, $note, $retention) {
            $revision = BlogPostRevision::query()->create([
                'post_id' => $post->id,
                'snapshot' => $snapshot,
                'type' => $type,
                'note' => $note,
                'created_by' => $userId,
            ]);
            $this->pruneOldRevisions($post, $retention);

            return $revision;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshotWithPivots(BlogPost $post): array
    {
        $snapshot = $this->revisionService->buildSnapshot($post);
        // buildSnapshot() copies raw attributes: false on a new post, 0 or
        // "0" once read back or submitted. Compare as a boolean.
        $snapshot['is_pinned'] = (bool) ($snapshot['is_pinned'] ?? false);
        $snapshot['category_ids'] = $post->categories()
            ->pluck('plg_dixlase_blog_categories.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $snapshot['tag_ids'] = $post->tags()
            ->pluck('plg_dixlase_blog_tags.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function snapshotHash(array $snapshot): string
    {
        $normalized = json_decode(
            (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
        ) ?? [];
        ksort($normalized);

        return hash('sha256', (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function pruneOldRevisions(BlogPost $post, int $retention): void
    {
        $total = BlogPostRevision::query()->where('post_id', $post->id)->count();
        $overflow = $total - $retention;
        if ($overflow <= 0) {
            return;
        }

        $ids = BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->where('is_protected', false)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($overflow)
            ->pluck('id');
        if ($ids->isNotEmpty()) {
            BlogPostRevision::query()->whereIn('id', $ids)->delete();
        }
    }
}
