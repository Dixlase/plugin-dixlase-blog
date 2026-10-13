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
 */

declare(strict_types=1);

namespace Plugins\DixlaseBlog\App\Http\Controllers\Front;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Plugins\DixlaseBlog\App\Http\Requests\Front\BlogCommentStoreRequest;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogComments;

class DixlaseBlogCommentController extends Controller
{
    /** Flash key read by the post page: "published" or "pending". */
    public const FLASH_KEY = 'dixlase_blog_comment';

    public function __construct(private readonly BlogComments $comments)
    {
    }

    /**
     * Route parameters are read by name; see DixlaseBlogFrontController.
     */
    public function store(BlogCommentStoreRequest $request): RedirectResponse
    {
        $post = BlogPost::query()
            ->currentlyVisible()
            ->where('slug', (string) $request->route('slug'))
            ->firstOrFail();

        abort_unless($this->comments->isOpen($post), 403);

        $postUrl = route('dixlase-blog::front.show', ['slug' => $post->slug]);

        // A filled honeypot is a bot. It is told the comment awaits
        // approval, so it learns nothing, and nothing is stored.
        if (filled($request->input('website'))) {
            return redirect()->to($postUrl.'#comment-form')
                ->with(self::FLASH_KEY, BlogComment::STATUS_PENDING);
        }

        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;
        if ($parentId !== null && ! $post->comments()->published()->whereKey($parentId)->exists()) {
            throw ValidationException::withMessages([
                'body' => __('dixlase-blog::front.comments.parent_missing'),
            ])->redirectTo($postUrl.'#comment-form');
        }

        $member = $request->isBySite() ? $this->comments->siteCommenter() : null;
        $body = $this->normaliseBody((string) $request->input('body'));

        $comment = BlogComment::create([
            'post_id' => $post->id,
            'parent_id' => $parentId,
            'member_id' => $member?->id,
            'author_name' => $member !== null
                ? (string) ($member->display_name ?: $member->account_name)
                : trim((string) $request->input('author_name')),
            'author_email' => $member !== null ? null : trim((string) $request->input('author_email')),
            'body' => $body,
            'status' => $this->comments->initialStatus($body, $member !== null),
            'lang' => app()->getLocale(),
            'ip_address' => $member !== null ? null : $request->ip(),
        ]);

        $anchor = $comment->isPublished() ? '#comment-'.$comment->id : '#comment-form';

        return redirect()->to($postUrl.$anchor)->with(self::FLASH_KEY, $comment->status);
    }

    /**
     * Unify line breaks and drop blank lines at either end; the text is
     * otherwise kept as written and escaped when shown.
     */
    private function normaliseBody(string $body): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $body), "\n ");
    }
}
