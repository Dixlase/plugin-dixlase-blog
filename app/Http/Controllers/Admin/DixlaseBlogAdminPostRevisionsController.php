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

namespace Plugins\DixlaseBlog\App\Http\Controllers\Admin;

use App\Helpers\AdminHelper;
use App\Presenters\Admin\RevisionDiffPresenter;
use App\Services\RevisionService;
use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogPostRevision;
use Plugins\DixlaseBlog\App\Services\BlogPostService;

class DixlaseBlogAdminPostRevisionsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;
    use AuthorizesRequests;

    public function __construct(
        private readonly BlogPostService $postService,
        private readonly RevisionService $revisionService,
        private readonly RevisionDiffPresenter $diffPresenter,
    ) {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    /**
     * All five revision endpoints take {post} as a plain int and resolve
     * the model manually rather than via Laravel's implicit model
     * binding — see DixlaseBlogAdminPostsController::edit doc-block for
     * the full reasoning. The same applies to {revisionId}; we keep
     * that explicit lookup against post_id so a revision id from a
     * different post cannot be addressed.
     */
    public function index(int $post): View
    {
        $post = BlogPost::query()->findOrFail($post);
        $revisions = $post->revisions()->with('creator')->paginate(20);

        return view('dixlase-blog::admin.posts.revisions.index', array_merge($this->viewParams, [
            'post' => $post,
            'revisions' => $revisions,
            'typeLabels' => $this->typeLabels(),
            'retention' => $this->revisionService->getRetentionCount(),
            'protectedCount' => $this->revisionService->countProtected($post),
        ]));
    }

    public function show(int $post, int $revisionId): View
    {
        $post = BlogPost::query()->findOrFail($post);
        $revision = BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->with('creator')
            ->findOrFail($revisionId);

        $currentSnapshot = $this->revisionService->buildSnapshot($post);
        $revisionSnapshot = (array) ($revision->snapshot ?? []);

        $diffs = [];
        foreach (['title', 'slug', 'content', 'excerpt'] as $field) {
            $left = (string) ($revisionSnapshot[$field] ?? '');
            $right = (string) ($currentSnapshot[$field] ?? '');
            if (! $this->diffPresenter->hasChanges($left, $right)) {
                continue;
            }
            $diffs[$field] = $this->diffPresenter->buildSideBySide($left, $right);
        }

        $metaDiffs = [];
        foreach (['featured_media_id', 'editor_type', 'status', 'lang', 'published_at', 'closed_at'] as $field) {
            $left = $revisionSnapshot[$field] ?? null;
            $right = $currentSnapshot[$field] ?? null;
            if ($left !== $right) {
                $metaDiffs[$field] = ['old' => $left, 'new' => $right];
            }
        }

        // Pivot diffs: show added/removed category and tag ids relative to current state.
        $pivotDiffs = [];
        foreach (['category_ids', 'tag_ids'] as $field) {
            $oldIds = array_map('intval', (array) ($revisionSnapshot[$field] ?? []));
            $newIds = $field === 'category_ids'
                ? $post->categories()->pluck('plg_dixlase_blog_categories.id')->map(fn ($id) => (int) $id)->all()
                : $post->tags()->pluck('plg_dixlase_blog_tags.id')->map(fn ($id) => (int) $id)->all();
            sort($oldIds);
            sort($newIds);
            if ($oldIds !== $newIds) {
                $pivotDiffs[$field] = [
                    'added' => array_values(array_diff($newIds, $oldIds)),
                    'removed' => array_values(array_diff($oldIds, $newIds)),
                ];
            }
        }

        return view('dixlase-blog::admin.posts.revisions.show', array_merge($this->viewParams, [
            'post' => $post,
            'revision' => $revision,
            'diffs' => $diffs,
            'metaDiffs' => $metaDiffs,
            'pivotDiffs' => $pivotDiffs,
            'hasChanges' => ! empty($diffs) || ! empty($metaDiffs) || ! empty($pivotDiffs),
            'typeLabels' => $this->typeLabels(),
        ]));
    }

    public function restore(int $post, int $revisionId): RedirectResponse
    {
        $post = BlogPost::query()->findOrFail($post);
        $revision = BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->findOrFail($revisionId);

        $this->postService->restoreRevision($revision, AdminHelper::getMember()?->id);

        return redirect()
            ->route('dixlase-blog::admin.blog.revisions.index', ['post' => $post->id])
            ->with('success', __('dixlase-blog::admin.flash.revision_restored'));
    }

    public function toggleProtection(int $post, int $revisionId): RedirectResponse
    {
        $post = BlogPost::query()->findOrFail($post);
        $revision = BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->findOrFail($revisionId);

        $revision->update(['is_protected' => ! $revision->is_protected]);

        return back()->with(
            'success',
            $revision->fresh()->is_protected
                ? __('dixlase-blog::admin.flash.revision_protect_enabled')
                : __('dixlase-blog::admin.flash.revision_protect_disabled'),
        );
    }

    /**
     * Update the free-text note attached to a revision. The core
     * <x-revision.diff> component always renders the note form, so a
     * matching endpoint is required even when audit logging via Actions
     * is not yet implemented for this plugin.
     */
    public function updateNote(Request $request, int $post, int $revisionId): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $post = BlogPost::query()->findOrFail($post);
        $revision = BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->findOrFail($revisionId);

        $revision->update(['note' => $data['note'] ?? null]);

        return redirect()
            ->route('dixlase-blog::admin.blog.revisions.show', [
                'post' => $post->id,
                'revisionId' => $revision->id,
            ])
            ->with('success', __('dixlase-blog::admin.flash.revision_note_updated'));
    }

    /**
     * @return array<string, string>
     */
    private function typeLabels(): array
    {
        return [
            BlogPostRevision::TYPE_AUTO => __('dixlase-blog::admin.revision.type_auto'),
            BlogPostRevision::TYPE_MANUAL => __('dixlase-blog::admin.revision.type_manual'),
            BlogPostRevision::TYPE_RESTORE_BACKUP => __('dixlase-blog::admin.revision.type_restore_backup'),
        ];
    }
}
