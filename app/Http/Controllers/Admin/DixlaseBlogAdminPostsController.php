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

use App\Enums\ContentEditorType;
use App\Helpers\AdminHelper;
use App\Helpers\LocaleHelper;
use App\Traits\AdminInterfaceTrait;
use App\Traits\AdminLoggedInTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostUpdateRequest;
use Illuminate\Database\Eloquent\Builder;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogComment;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogContentRenderer;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;
use Plugins\DixlaseBlog\App\Services\BlogSeoMeta;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

class DixlaseBlogAdminPostsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;
    use AuthorizesRequests;

    public function __construct(
        private readonly BlogPostService $postService,
        private readonly BlogSeoMeta $seoMeta,
    ) {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function index(): View
    {
        $posts = BlogPost::query()
            ->with(['categories', 'tags', 'author'])
            // Comment counts for the list, without a query per row.
            ->withCount([
                'comments as published_comments_count' => fn (Builder $q) => $q->where('status', BlogComment::STATUS_PUBLISHED),
                'comments as pending_comments_count' => fn (Builder $q) => $q->where('status', BlogComment::STATUS_PENDING),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dixlase-blog::admin.posts.index', array_merge($this->viewParams, [
            'posts' => $posts,
            'pendingCommentsUrl' => route('dixlase-blog::admin.blog.comments.index', ['status' => BlogComment::STATUS_PENDING]),
            'permalinks' => $posts->getCollection()->mapWithKeys(fn (BlogPost $post) => [
                $post->id => route('dixlase-blog::front.show', ['slug' => $post->slug]),
            ])->all(),
        ]));
    }

    public function create(): View
    {
        $post = new BlogPost();

        return view('dixlase-blog::admin.posts.create', array_merge($this->viewParams, $this->languageParams($post), $this->seoMeta->formData($post), [
            'post' => $post,
            'categoryTree' => $this->loadCategoryTree(),
            'selectedCategoryIds' => [],
            'tagNames' => [],
            'commentStatusOptions' => $this->commentStatusOptions(),
        ]));
    }

    public function store(BlogPostStoreRequest $request): RedirectResponse
    {
        $post = $this->postService->create(
            $request->validated(),
            AdminHelper::getMember()?->id,
        );
        $this->seoMeta->save($post, $request->validated());

        return redirect()
            ->route('dixlase-blog::admin.blog.edit', ['post' => $post->id])
            ->with('success', __('dixlase-blog::admin.flash.post_created'));
    }

    /**
     * Route-bound model resolution.
     *
     * We deliberately avoid both Laravel's implicit model binding
     * (BlogPost $post) and positional scalar binding (int $post), and
     * read the {post} parameter by NAME from the Request instead.
     * Laravel passes route parameters to controller methods by position,
     * so a parameter added by any surrounding route group (the admin
     * routes once lived in routes/web.php, which DixlaseMultilingual also
     * mounts under /{locale}) would land in the wrong argument. Reading
     * by name is unaffected by such groups. This mirrors DixlaseLegal's
     * "resolve manually from the request" approach.
     */
    public function edit(Request $request): View
    {
        $post = BlogPost::query()
            ->with(['categories', 'tags', 'featuredMedia'])
            ->findOrFail((int) $request->route('post'));

        // An HTML body is script-capable; see BlogScriptAuthoringPolicy.
        abort_unless(BlogScriptAuthoringPolicy::canEditEditorType(AdminHelper::getMember(), $post->editor_type), 403);

        return view('dixlase-blog::admin.posts.edit', array_merge($this->viewParams, $this->languageParams($post), $this->seoMeta->formData($post), [
            'post' => $post,
            'categoryTree' => $this->loadCategoryTree(),
            'selectedCategoryIds' => $post->categories->pluck('id')->all(),
            'tagNames' => $post->tags->pluck('name')->all(),
            'commentStatusOptions' => $this->commentStatusOptions(),
        ]));
    }

    public function update(BlogPostUpdateRequest $request): RedirectResponse
    {
        $post = BlogPost::query()->findOrFail((int) $request->route('post'));

        $this->postService->update(
            $post,
            $request->validated(),
            AdminHelper::getMember()?->id,
        );
        $this->seoMeta->save($post, $request->validated());

        return redirect()
            ->route('dixlase-blog::admin.blog.edit', ['post' => $post->id])
            ->with('success', __('dixlase-blog::admin.flash.post_updated'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        BlogPost::query()
            ->findOrFail((int) $request->route('post'))
            ->delete();

        return redirect()
            ->route('dixlase-blog::admin.blog.index')
            ->with('success', __('dixlase-blog::admin.flash.post_trashed'));
    }

    public function trash(): View
    {
        $posts = BlogPost::onlyTrashed()
            ->with(['categories', 'tags', 'author'])
            ->orderByDesc('deleted_at')
            ->paginate(20);

        return view('dixlase-blog::admin.posts.trash', array_merge($this->viewParams, [
            'posts' => $posts,
        ]));
    }

    public function restore(Request $request): RedirectResponse
    {
        $post = BlogPost::onlyTrashed()->findOrFail((int) $request->route('id'));
        $slug = $post->slug;
        $post = $this->postService->restoreFromTrash($post);

        return redirect()
            ->route('dixlase-blog::admin.blog.trash')
            ->with('success', $post->slug === $slug
                ? __('dixlase-blog::admin.flash.post_restored')
                : __('dixlase-blog::admin.flash.post_restored_renamed', ['slug' => $post->slug]));
    }

    public function forceDelete(Request $request): RedirectResponse
    {
        $post = BlogPost::onlyTrashed()->findOrFail((int) $request->route('id'));
        $post->forceDelete();

        return redirect()
            ->route('dixlase-blog::admin.blog.trash')
            ->with('success', __('dixlase-blog::admin.flash.post_deleted'));
    }

    /**
     * Server-side preview render endpoint. Core's content editor
     * (previewRender="server") POSTs the current { content, editor_type } here on
     * each debounced edit, receives the rendered HTML in JSON, and pushes
     * it into the iframe via postMessage. It renders through
     * BlogContentRenderer, as the public post page does, so the preview
     * shows what will be published. HTML from a member who may not author
     * scripts is shown as text, as it could not be saved anyway.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:500000'],
            'editor_type' => ['required', 'string'],
        ]);

        $editorType = ContentEditorType::tryFromSlug($data['editor_type']);
        if ($editorType === ContentEditorType::HTML && ! BlogScriptAuthoringPolicy::canAuthorScripts(AdminHelper::getMember())) {
            $editorType = null;
        }

        $html = app(BlogContentRenderer::class)->render($data['content'] ?? '', $editorType);

        return response()->json(['html' => $html]);
    }

    /**
     * Iframe shell view for the live preview pane. Returns a minimal
     * blog-post layout that listens for postMessage("dixlase-preview-update")
     * from the parent window and swaps the title / content nodes.
     *
     * Initial render uses the saved post's content (rendered via
     * BlogContentRenderer) so the iframe is not blank for the
     * pre-first-edit moment; subsequent edits flow in via postMessage.
     * For create-mode there is no $post, so the iframe loads empty and
     * the very first edit fills it in.
     */
    public function previewFrame(Request $request): View
    {
        // Allow same-origin iframe embedding for the admin preview only.
        $request->attributes->set('csp_frame_ancestors_self', true);

        // Manual lookup keyed by route param name (see edit() doc-block
        // for why we avoid positional / implicit binding here). The
        // route declares {post?} so the param may be absent in
        // create-mode previews.
        $postId = $request->route('post');
        $resolvedPost = $postId !== null ? BlogPost::query()->with('featuredMedia')->find((int) $postId) : null;

        $initialContent = '';
        $initialTitle = '';
        $initialFeatured = null;
        if ($resolvedPost) {
            $media = $resolvedPost->featuredMedia;
            if ($media !== null) {
                $initialFeatured = [
                    'url' => asset('storage/media/'.$media->path),
                    'alt' => (string) ($media->alt_text ?: $resolvedPost->title),
                ];
            }
            $initialTitle = (string) $resolvedPost->title;
            $initialContent = app(BlogContentRenderer::class)->render(
                $resolvedPost->content,
                $resolvedPost->editor_type,
            );
        }

        return view('dixlase-blog::admin.posts.preview-frame', [
            'post' => $resolvedPost,
            'initialTitle' => $initialTitle,
            'initialContent' => $initialContent,
            'initialFeatured' => $initialFeatured,
        ]);
    }

    /**
     * Choices for a post's comments. "Inherit" names what the blog setting
     * currently gives.
     *
     * @return array<string, string>
     */
    private function commentStatusOptions(): array
    {
        $blogDefault = app(BlogSettings::class)->commentsEnabled()
            ? __('dixlase-blog::admin.post.comment_status_open')
            : __('dixlase-blog::admin.post.comment_status_closed');

        return [
            'inherit' => __('dixlase-blog::admin.post.comment_status_inherit', ['state' => $blogDefault]),
            'open' => __('dixlase-blog::admin.post.comment_status_open'),
            'closed' => __('dixlase-blog::admin.post.comment_status_closed'),
        ];
    }

    /**
     * Source-language picker for the post form.
     *
     * The picker shows only while DixlaseMultilingual's locale URLs are on;
     * a single-language site has nothing to choose and the post takes the
     * site's default locale. The translate button comes from
     * DixlaseMultilingual itself (see the post form's right sidebar).
     *
     * @return array{multilingualEnabled: bool, languageOptions: array<string, string>, langValue: string}
     */
    private function languageParams(BlogPost $post): array
    {
        return [
            'multilingualEnabled' => (bool) config('dixlase_multilingual.locale_url_routing_enabled', false),
            'languageOptions' => LocaleHelper::supportedLocaleOptions(),
            'langValue' => (string) old('lang', $post->exists ? $post->sourceLocale() : LocaleHelper::getSiteDefaultLocale()),
        ];
    }

    /**
     * Flat list of categories with depth info, for rendering an indented
     * <select> or checkbox tree in the admin form.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BlogCategory>
     */
    private function loadCategoryTree(): \Illuminate\Database\Eloquent\Collection
    {
        return BlogCategory::query()
            ->orderBy('depth')
            ->orderBy('display_order')
            ->orderBy('label')
            ->get();
    }
}
