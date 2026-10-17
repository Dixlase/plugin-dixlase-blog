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

use App\Helpers\CaptchaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Front\BlogCommentStoreRequest;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;
use Plugins\DixlaseBlog\App\Services\BlogBreadcrumbs;
use Plugins\DixlaseBlog\App\Services\BlogCommentCaptchaFormProvider;
use Plugins\DixlaseBlog\App\Services\BlogComments;
use Plugins\DixlaseBlog\App\Services\BlogContentRenderer;
use Plugins\DixlaseBlog\App\Services\BlogPostNeighbours;
use Plugins\DixlaseBlog\App\Services\BlogShareLinks;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

class DixlaseBlogFrontController extends Controller
{
    /** Longest search query used; the rest is dropped. */
    private const SEARCH_MAX_LENGTH = 100;

    public function __construct(
        private readonly BlogContentRenderer $renderer,
        private readonly BlogSettings $settings,
        private readonly BlogPostNeighbours $neighbours,
        private readonly BlogShareLinks $shareLinks,
        private readonly BlogBreadcrumbs $breadcrumbs,
        private readonly BlogComments $comments,
    ) {
    }

    public function index(): View
    {
        // Pinned posts first; the archives and the feed keep date order.
        $posts = BlogPost::query()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->withPublishedCommentCount()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate($this->settings->postsPerPage());

        return view('dixlase-blog::front.index', [
            'posts' => $posts,
            'breadcrumbs' => $this->breadcrumbs->forIndex(),
        ]);
    }

    /**
     * Route parameters are read by name. With locale URLs on, these routes
     * are mounted a second time under /{locale}, and Laravel hands controller
     * arguments over by position, so a `string $slug` argument would
     * receive the locale there.
     */
    public function show(Request $request): View
    {
        $post = BlogPost::query()
            ->currentlyVisible()
            ->where('slug', $this->slug($request))
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->firstOrFail();

        return view('dixlase-blog::front.show', $this->postViewData($request, $post));
    }

    /**
     * A post in any status (draft, scheduled, closed) as the site will show
     * it, for the "Preview" button of the post editor. Routed under the
     * admin URL (routes/admin.php): core keeps the admin session apart
     * from the front one, so only there is the admin login known. Only
     * members who may edit posts see it; anyone else gets the same 404 as
     * for a missing post, so a draft's existence does not leak. The page
     * is kept out of search engines and caches.
     */
    public function preview(Request $request): Response
    {
        abort_if($this->comments->siteCommenter() === null, 404);

        $post = BlogPost::query()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->findOrFail((int) $request->route('post'));

        return response()
            ->view('dixlase-blog::front.show', array_merge($this->postViewData($request, $post), [
                'isPreview' => true,
            ]))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array<string, mixed>
     */
    private function postViewData(Request $request, BlogPost $post): array
    {
        $postUrl = route('dixlase-blog::front.show', ['slug' => $post->slug]);

        return array_merge($this->commentParams($request, $post), [
            'post' => $post,
            'contentHtml' => $this->renderer->renderPost($post),
            'previousPost' => $this->neighbours->previous($post),
            'nextPost' => $this->neighbours->next($post),
            'relatedPosts' => $this->neighbours->related($post),
            'postUrl' => $postUrl,
            'shareLinks' => $this->shareLinks->for($postUrl, (string) $post->localized('title')),
            'breadcrumbs' => $this->breadcrumbs->forPost($post, $postUrl),
        ]);
    }

    public function category(Request $request): View
    {
        $category = BlogCategory::query()
            ->where('slug', $this->slug($request))
            ->firstOrFail();

        $posts = $category->posts()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->withPublishedCommentCount()
            ->orderByDesc('published_at')
            ->paginate($this->settings->postsPerPage());

        return view('dixlase-blog::front.archive', [
            'archiveType' => 'category',
            'archiveTerm' => $category,
            'breadcrumbs' => $this->breadcrumbs->forCategory($category),
            'archiveLabel' => $category->localized('label'),
            'archiveLang' => $category->localizedLang('label'),
            'posts' => $posts,
        ]);
    }

    public function tag(Request $request): View
    {
        $tag = BlogTag::query()
            ->where('slug', $this->slug($request))
            ->firstOrFail();

        $posts = $tag->posts()
            ->currentlyVisible()
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->withPublishedCommentCount()
            ->orderByDesc('published_at')
            ->paginate($this->settings->postsPerPage());

        return view('dixlase-blog::front.archive', [
            'archiveType' => 'tag',
            'archiveTerm' => $tag,
            'breadcrumbs' => $this->breadcrumbs->forTag($tag),
            'archiveLabel' => $tag->localized('name'),
            'archiveLang' => $tag->localizedLang('name'),
            'posts' => $posts,
        ]);
    }

    /**
     * Posts of one month (/blog/{year}/{month}) or one day
     * (/blog/{year}/{month}/{day}), by publish date in the site's time zone.
     */
    public function date(Request $request): View
    {
        $year = (int) $request->route('year');
        $month = (int) $request->route('month');
        $day = $request->route('day') !== null ? (int) $request->route('day') : null;

        abort_unless(checkdate($month, $day ?? 1, $year), 404);

        $start = Carbon::create($year, $month, $day ?? 1)->startOfDay();
        $end = $day === null ? $start->copy()->endOfMonth() : $start->copy()->endOfDay();

        $posts = BlogPost::query()
            ->currentlyVisible()
            ->whereBetween('published_at', [$start, $end])
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->withPublishedCommentCount()
            ->orderByDesc('published_at')
            ->paginate($this->settings->postsPerPage());

        $locale = app()->getLocale();
        $format = __($day === null ? 'dixlase-blog::front.archive.month_format' : 'dixlase-blog::front.archive.day_format');

        return view('dixlase-blog::front.archive', [
            'archiveType' => 'date',
            'archiveTerm' => $start,
            'breadcrumbs' => $this->breadcrumbs->forDate($start, $day !== null),
            'archiveLabel' => $start->copy()->locale($locale)->isoFormat($format),
            'archiveLang' => $locale,
            'posts' => $posts,
        ]);
    }

    /**
     * Search the visible posts. Every word must appear in the title, the
     * excerpt or the body. Only the text a post was written in is
     * searched: translations live in the translation plugin's tables,
     * which the blog may not query, and core's TranslationResolver cannot
     * search.
     * Results are not for search engines (X-Robots-Tag).
     */
    public function search(Request $request): Response
    {
        $query = Str::limit(trim((string) $request->query('q', '')), self::SEARCH_MAX_LENGTH, '');
        // Half- and full-width spaces both separate words.
        $words = array_values(array_filter(
            preg_split('/[\s\x{3000}]+/u', $query) ?: [],
            fn (string $word) => $word !== '',
        ));

        $posts = BlogPost::query()
            ->currentlyVisible()
            ->when($words === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
                    $q->where(function ($w) use ($like) {
                        // "!" escapes, as SQLite has no default escape character.
                        $w->whereRaw("title LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("excerpt LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("content LIKE ? ESCAPE '!'", [$like]);
                    });
                }
            })
            ->with(['categories', 'tags', 'featuredMedia', 'author'])
            ->withPublishedCommentCount()
            ->orderByDesc('published_at')
            ->paginate($this->settings->postsPerPage())
            ->withQueryString();

        return response()
            ->view('dixlase-blog::front.archive', [
                'archiveType' => 'search',
                'archiveTerm' => $query,
                'breadcrumbs' => $this->breadcrumbs->forSearch($request->fullUrl()),
                'archiveLabel' => $query,
                'archiveLang' => app()->getLocale(),
                'posts' => $posts,
            ])
            ->header('X-Robots-Tag', 'noindex, follow');
    }

    /**
     * The comments section of a post page. ?replyto= names the published
     * comment being answered; without JavaScript, the reply links use it.
     *
     * @return array<string, mixed>
     */
    private function commentParams(Request $request, BlogPost $post): array
    {
        $open = $this->comments->isOpen($post);
        $tree = $this->comments->tree($post);
        $siteCommenter = $open ? $this->comments->siteCommenter() : null;

        $replyTo = null;
        if ($open && is_numeric($request->query('replyto'))) {
            $replyTo = $post->comments()->published()->find((int) $request->query('replyto'));
        }

        $captchaEnabled = $open && $siteCommenter === null
            && CaptchaHelper::shouldShowCaptcha(BlogCommentCaptchaFormProvider::FORM_KEY);

        return [
            'commentsShown' => $open || $tree !== [],
            'commentsOpen' => $open,
            'commentTree' => $tree,
            'commentCount' => $this->comments->publishedCount($post),
            'commentReplyTo' => $replyTo,
            'commentSiteCommenter' => $siteCommenter,
            'commentPrivacyUrl' => $open ? $this->comments->privacyPolicyUrl() : null,
            'commentFlash' => session(DixlaseBlogCommentController::FLASH_KEY),
            'commentBodyMax' => BlogCommentStoreRequest::BODY_MAX,
            'commentNameMax' => BlogCommentStoreRequest::NAME_MAX,
            'captchaEnabled' => $captchaEnabled,
            'captchaWidget' => $captchaEnabled ? CaptchaHelper::renderWidget(BlogCommentCaptchaFormProvider::FORM_KEY) : null,
        ];
    }

    private function slug(Request $request): string
    {
        return (string) $request->route('slug');
    }
}
