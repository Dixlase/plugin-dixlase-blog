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
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostStoreRequest;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\BlogPostUpdateRequest;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Services\BlogContentRenderer;
use Plugins\DixlaseBlog\App\Services\BlogPostService;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;

class DixlaseBlogAdminPostsController extends Controller
{
    use AdminInterfaceTrait;
    use AdminLoggedInTrait;
    use AuthorizesRequests;

    public function __construct(private readonly BlogPostService $postService)
    {
        $this->initialize();
        $this->initializeAfterLogin();
    }

    public function index(): View
    {
        $posts = BlogPost::query()
            ->with(['categories', 'tags', 'author'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dixlase-blog::admin.posts.index', array_merge($this->viewParams, [
            'posts' => $posts,
        ]));
    }

    public function create(): View
    {
        $post = new BlogPost();

        return view('dixlase-blog::admin.posts.create', array_merge($this->viewParams, $this->languageParams($post), [
            'post' => $post,
            'categoryTree' => $this->loadCategoryTree(),
            'selectedCategoryIds' => [],
            'tagNames' => [],
        ]));
    }

    public function store(BlogPostStoreRequest $request): RedirectResponse
    {
        $post = $this->postService->create(
            $request->validated(),
            AdminHelper::getMember()?->id,
        );

        return redirect()
            ->route('dixlase-blog::admin.blog.edit', ['post' => $post->id])
            ->with('success', __('dixlase-blog::admin.flash.post_created'));
    }

    /**
     * Route-bound model resolution.
     *
     * We deliberately avoid both Laravel's implicit model binding
     * (BlogPost $post) and positional scalar binding (int $post). The
     * admin path segment comes from the admin_url security setting (a
     * prefix plus a string each site chooses). With locale URLs on,
     * DixlaseMultilingual includes this routes/web.php a second time
     * inside a {locale} prefix group (PluginHelper::loadEnabledWebRoutes()
     * mounted under /{locale}/...), so the effective route becomes
     * /{locale}/<admin-url>/blog/{post}/edit.
     *
     * Under that two-parameter route Laravel hands the controller the
     * URL params *positionally* (locale first, post second), so any
     * controller signature like `edit(int $post)` receives the locale
     * string in the first slot and raises a TypeError. The trace
     * looked like edit(string, string).
     *
     * The fix is to take the Request and look up the model by route
     * parameter NAME, which is unaffected by the wrapping {locale}
     * prefix or any other group added downstream. This mirrors
     * DixlaseLegal's "resolve manually from the request" approach.
     */
    public function edit(Request $request): View
    {
        $post = BlogPost::query()
            ->with(['categories', 'tags', 'featuredMedia'])
            ->findOrFail((int) $request->route('post'));

        // An HTML body is script-capable; see BlogScriptAuthoringPolicy.
        abort_unless(BlogScriptAuthoringPolicy::canEditEditorType(AdminHelper::getMember(), $post->editor_type), 403);

        return view('dixlase-blog::admin.posts.edit', array_merge($this->viewParams, $this->languageParams($post), [
            'post' => $post,
            'categoryTree' => $this->loadCategoryTree(),
            'selectedCategoryIds' => $post->categories->pluck('id')->all(),
            'tagNames' => $post->tags->pluck('name')->all(),
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
        $post->restore();

        return redirect()
            ->route('dixlase-blog::admin.blog.trash')
            ->with('success', __('dixlase-blog::admin.flash.post_restored'));
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
     * Server-side preview render endpoint. The post-form Alpine editor
     * (blogPostEditor) POSTs the current { content, editor_type } here on
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
        $resolvedPost = $postId !== null ? BlogPost::query()->find((int) $postId) : null;

        $initialContent = '';
        $initialTitle = '';
        if ($resolvedPost) {
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
        ]);
    }

    /**
     * Flat list of categories with depth info, for rendering an indented
     * <select> or checkbox tree in the admin form.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BlogCategory>
     */
    /**
     * Source-language picker and translation-editor link for the post form.
     *
     * The picker shows only while DixlaseMultilingual's locale URLs are on;
     * a single-language site has nothing to choose and the post takes the
     * site's default locale. The translation link appears when
     * DixlaseMultilingual is installed and the post has been saved.
     *
     * @return array{multilingualEnabled: bool, languageOptions: array<string, string>, langValue: string, translationUrl: string|null}
     */
    private function languageParams(BlogPost $post): array
    {
        $translationRoute = 'dixlase-multilingual::admin.multilingual.translations.edit';

        return [
            'multilingualEnabled' => (bool) config('dixlase_multilingual.locale_url_routing_enabled', false),
            'languageOptions' => LocaleHelper::supportedLocaleOptions(),
            'langValue' => (string) old('lang', $post->exists ? $post->sourceLocale() : LocaleHelper::getSiteDefaultLocale()),
            'translationUrl' => $post->exists && Route::has($translationRoute)
                ? route($translationRoute, ['type' => 'dixlase-blog:post', 'id' => $post->id])
                : null,
        ];
    }

    private function loadCategoryTree(): \Illuminate\Database\Eloquent\Collection
    {
        return BlogCategory::query()
            ->orderBy('depth')
            ->orderBy('display_order')
            ->orderBy('label')
            ->get();
    }
}
