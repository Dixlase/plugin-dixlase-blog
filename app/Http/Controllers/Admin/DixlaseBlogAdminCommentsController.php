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

use App\Enums\MemberRole;
use App\Helpers\AdminHelper;
use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Services\BlogComments;

/**
 * The comments screen: every post's comments by state, with approve,
 * unpublish, spam, trash, restore and permanent delete (ADMIN), singly or
 * in bulk, and replies as the site.
 */
class DixlaseBlogAdminCommentsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;

    /** The tabs, in order. "trash" is the soft-deleted comments. */
    public const TABS = ['pending', 'published', 'spam', 'trash'];

    /** Bulk actions per tab. */
    private const BULK_ACTIONS = [
        'pending' => ['approve', 'spam', 'trash'],
        'published' => ['unpublish', 'spam', 'trash'],
        'spam' => ['approve', 'trash'],
        'trash' => ['restore', 'delete'],
    ];

    public function __construct(private readonly BlogComments $comments)
    {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->query('status'), self::TABS, true) ? (string) $request->query('status') : 'pending';

        $comments = $this->tabQuery($tab)
            ->with(['post' => fn ($q) => $q->withTrashed(), 'parent'])
            ->orderByDesc($tab === 'trash' ? 'deleted_at' : 'created_at')
            ->paginate(20)
            ->withQueryString();

        $canDelete = $this->canDeletePermanently();

        return view('dixlase-blog::admin.comments.index', array_merge($this->viewParams, [
            'tab' => $tab,
            'tabs' => array_map(fn (string $key) => [
                'key' => $key,
                'label' => __('dixlase-blog::admin.comments.tab_'.$key),
                'count' => $this->tabQuery($key)->count(),
                'url' => route('dixlase-blog::admin.blog.comments.index', ['status' => $key]),
                'active' => $key === $tab,
            ], self::TABS),
            'comments' => $comments,
            'rows' => $comments->getCollection()->map(fn (BlogComment $comment) => $this->row($comment, $tab, $canDelete))->all(),
            'bulkActions' => collect(self::BULK_ACTIONS[$tab])
                ->reject(fn (string $action) => $action === 'delete' && ! $canDelete)
                ->mapWithKeys(fn (string $action) => [$action => __('dixlase-blog::admin.comments.action_'.$action)])
                ->all(),
        ]));
    }

    /**
     * Approve (published), unpublish (pending) or mark as spam.
     */
    public function status(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(BlogComment::STATUSES)],
        ]);

        $comment = BlogComment::query()->findOrFail((int) $request->route('comment'));
        $comment->update(['status' => $data['status']]);

        return back()->with('success', __('dixlase-blog::admin.flash.comment_'.$data['status']));
    }

    public function destroy(Request $request): RedirectResponse
    {
        BlogComment::query()->findOrFail((int) $request->route('comment'))->delete();

        return back()->with('success', __('dixlase-blog::admin.flash.comment_trashed'));
    }

    public function restore(Request $request): RedirectResponse
    {
        BlogComment::onlyTrashed()->findOrFail((int) $request->route('comment'))->restore();

        return back()->with('success', __('dixlase-blog::admin.flash.comment_restored'));
    }

    /**
     * Delete a trashed comment for good; the database removes its replies
     * with it.
     */
    public function forceDelete(Request $request): RedirectResponse
    {
        BlogComment::onlyTrashed()->findOrFail((int) $request->route('comment'))->forceDelete();

        return back()->with('success', __('dixlase-blog::admin.flash.comment_deleted'));
    }

    /**
     * Reply as the site. Replying to a comment waiting for approval
     * approves it, as the reply would otherwise answer nothing visible.
     */
    public function reply(Request $request): RedirectResponse
    {
        $comment = BlogComment::query()->findOrFail((int) $request->route('comment'));
        $field = 'reply_body.'.$comment->id;

        $request->validate(
            [$field => ['required', 'string', 'max:5000']],
            [],
            [$field => __('dixlase-blog::admin.comments.reply_label')],
        );
        abort_if($comment->status === BlogComment::STATUS_SPAM, 422);

        $member = AdminHelper::getMember();
        if ($comment->status === BlogComment::STATUS_PENDING) {
            $comment->update(['status' => BlogComment::STATUS_PUBLISHED]);
        }

        BlogComment::create([
            'post_id' => $comment->post_id,
            'parent_id' => $comment->id,
            'member_id' => $member?->id,
            'author_name' => (string) ($member?->display_name ?: $member?->account_name ?: config('app.name')),
            'body' => trim(str_replace(["\r\n", "\r"], "\n", (string) $request->input($field)), "\n "),
            'status' => BlogComment::STATUS_PUBLISHED,
            'lang' => $comment->lang,
        ]);

        return back()->with('success', __('dixlase-blog::admin.flash.comment_replied'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'unpublish', 'spam', 'trash', 'restore', 'delete'])],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => __('dixlase-blog::admin.comments.bulk_none_selected'),
        ]);

        if ($data['action'] === 'delete') {
            abort_unless($this->canDeletePermanently(), 403);
        }

        $ids = array_map('intval', $data['ids']);
        $live = BlogComment::query()->whereKey($ids);
        $trashed = BlogComment::onlyTrashed()->whereKey($ids);

        $count = match ($data['action']) {
            'approve' => $live->update(['status' => BlogComment::STATUS_PUBLISHED]),
            'unpublish' => $live->update(['status' => BlogComment::STATUS_PENDING]),
            'spam' => $live->update(['status' => BlogComment::STATUS_SPAM]),
            'trash' => $live->get()->each->delete()->count(),
            'restore' => $trashed->get()->each->restore()->count(),
            'delete' => $trashed->get()->each->forceDelete()->count(),
        };

        return back()->with('success', __('dixlase-blog::admin.flash.comments_bulk', ['count' => $count]));
    }

    /**
     * @return Builder<BlogComment>
     */
    private function tabQuery(string $tab): Builder
    {
        return $tab === 'trash'
            ? BlogComment::onlyTrashed()
            : BlogComment::query()->where('status', $tab);
    }

    private function canDeletePermanently(): bool
    {
        $role = AdminHelper::getMember()?->role;

        return $role instanceof MemberRole && $role->canAccess(MemberRole::ADMIN);
    }

    /**
     * Everything a table row shows, prepared here as admin views take no
     * logic.
     *
     * @return array<string, mixed>
     */
    private function row(BlogComment $comment, string $tab, bool $canDelete): array
    {
        $post = $comment->post;

        return [
            'id' => $comment->id,
            'author' => $comment->author_name,
            'email' => $comment->author_email,
            'ip' => $comment->ip_address,
            'by_site' => $comment->isBySite(),
            'body' => $comment->body,
            'excerpt' => Str::limit(preg_replace('/\s+/u', ' ', $comment->body) ?? '', 200),
            'date' => $comment->created_at,
            'post_title' => $post?->title,
            'post_edit_url' => $post !== null ? route('dixlase-blog::admin.blog.edit', ['post' => $post->id]) : null,
            'post_url' => $post !== null && $post->isCurrentlyVisible()
                ? route('dixlase-blog::front.show', ['slug' => $post->slug]).'#comment-'.$comment->id
                : null,
            'parent_author' => $comment->parent?->author_name,
            'status_label' => __('dixlase-blog::admin.comments.status_'.$comment->status),
            'status_variant' => match ($comment->status) {
                BlogComment::STATUS_PUBLISHED => 'green',
                BlogComment::STATUS_SPAM => 'red',
                default => 'yellow',
            },
            'can_approve' => $tab !== 'trash' && $comment->status !== BlogComment::STATUS_PUBLISHED,
            'can_unpublish' => $tab !== 'trash' && $comment->status === BlogComment::STATUS_PUBLISHED,
            'can_spam' => $tab !== 'trash' && $comment->status !== BlogComment::STATUS_SPAM,
            'can_reply' => $tab !== 'trash' && $comment->status !== BlogComment::STATUS_SPAM,
            'can_trash' => $tab !== 'trash',
            'can_restore' => $tab === 'trash',
            'can_delete' => $tab === 'trash' && $canDelete,
        ];
    }
}
