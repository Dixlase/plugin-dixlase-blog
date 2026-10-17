{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Shared form partial used by both create and edit. The wrapping <form>
tag is rendered by the parent so the right-sidebar can sit inside the
same form element.

Layout (centre column): core's shared content editor
(<x-form-content-editor>) with its split-pane live preview. The preview
frame is the blog's own (previewFrame), and every edit is rendered by the
blog's preview endpoint (previewRender="server"), so the preview applies
the same rules as the post page (BlogContentRenderer). The title and the
featured image travel to the frame from the sidebar (blogPreviewTitle,
blogFeaturedPreview).

In edit mode the editor-type selector is hidden (showEditorSelector =
false). Once a post has been saved with an editor type, switching types
loses content fidelity, so the editor type becomes a locked badge in the
sidebar's "Meta info" section instead. Same convention the FrontPage
admin uses.

With the HTML editor, members who may author scripts also get the CSS and
JavaScript tabs, as for pages (not in Simple mode). The preview applies
the CSS as it is typed and never runs the JavaScript.
--}}

@php
    $isEditMode = $post->exists;
    $editorTypeSlug = old('editor_type', $post->editor_type instanceof \App\Enums\ContentEditorType ? $post->editor_type->slug() : 'markdown');
    $statusSlug = old('status', $post->status instanceof \App\Enums\ContentStatus ? $post->status->slug() : 'draft');
    // Reuse core presenter so the editor radio cards pick up the same
    // labels / icons / descriptions as the FrontPage admin (otherwise the
    // Alpine $t() calls inside <x-form-content-editor> see an empty
    // translations bag and render the raw "common.content_editor.*"
    // keys as placeholders).
    $editorIcons = \App\Presenters\Admin\ContentEditorPresenter::editorIcons();
    $editorTranslations = \App\Presenters\Admin\ContentEditorPresenter::editorTranslations();

    $previewFrameUrl = $isEditMode
        ? route('dixlase-blog::admin.blog.previewFrame', ['post' => $post->id])
        : route('dixlase-blog::admin.blog.previewFrame');

    $assetTabs = \Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy::canAuthorScripts(auth('member')->user())
        && ! \Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy::isSimpleMode();
@endphp

@csrf
@if ($isEditMode)
    @method('PUT')
@endif

{{-- ===== Centre column: shared editor with live preview ===== --}}
@push('styles')
<style @cspNonce>
/* Let the main column shrink below the preview iframe's width, so it
   fits beside the right sidebar again after the sidebar is reopened
   (flex items default to min-width: auto). The front page and pages
   editors set the same. */
#admin-main-content { min-width: 0; }
</style>
@endpush

<x-form-content-editor
    class="dixlase-blog-editor"
    storageType="database"
    :editorType="$editorTypeSlug"
    :content="old('content', $post->content ?? '')"
    :showStorageSelector="false"
    :showEditorSelector="!$isEditMode"
    contentFieldName="content"
    editorFieldName="editor_type"
    :editorIcons="$editorIcons"
    :editorTranslations="$editorTranslations"
    :preview="true"
    :previewFrameUrl="$previewFrameUrl"
    :previewUrl="route('dixlase-blog::admin.blog.preview')"
    previewRender="server"
    :assetTabs="$assetTabs"
    :customCss="old('custom_css', $post->custom_css ?? '')"
    :customJs="old('custom_js', $post->custom_js ?? '')"
    :cssPlaceholder="__('dixlase-blog::admin.post_form.css_placeholder')"
    :jsPlaceholder="__('dixlase-blog::admin.post_form.js_placeholder')"
/>
<x-form-error name="custom_css" />
<x-form-error name="custom_js" />
<x-form-error name="content" />

{{-- ===== Right sidebar ===== --}}
@include('dixlase-blog::admin.posts.partials._right-sidebar', [
    'post' => $post,
    'isEditMode' => $isEditMode,
    'editorTypeSlug' => $editorTypeSlug,
    'statusSlug' => $statusSlug,
    'categoryTree' => $categoryTree,
    'selectedCategoryIds' => $selectedCategoryIds,
    'tagNames' => $tagNames,
])
