{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Shared form partial used by both create and edit. The wrapping <form>
tag is rendered by the parent so the right-sidebar can sit inside the
same form element.

Layout (centre column):
  - Split pane container hosting <x-form-content-editor> on the left and
    core's <x-content-editor.preview-pane> on the right.
  - No bg-white card wrapping the editor — matches the FrontPage admin
    layout the operator pointed at; the form-textarea inside the
    content-editor brings its own framing.
  - In edit mode the editor-type selector is hidden (showEditorSelector
    = false). Once a post has been saved with an editor type, switching
    types loses content fidelity, so the editor type becomes a locked
    badge in the sidebar's "Meta info" section instead. Same convention
    DixlaseLegal and the FrontPage admin use.

The Alpine scope that runs the split pane and live preview is
blogPostEditor (resources/src/js/blog-post-editor.js), which mixes
core's previewMixin and splitPaneMixin via the window.Dixlase.mixins
runtime API.
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
@endphp

@csrf
@if ($isEditMode)
    @method('PUT')
@endif

{{-- ===== Centre column: split pane (editor + live preview) ===== --}}
{{-- The "dixlase-blog-editor" marker class scopes the CSS overrides below
     so they do not affect <x-form-content-editor> usages elsewhere. --}}
@push('styles')
<style @cspNonce>
/* The form-content-editor's Markdown panel ships its own client-side
   side-by-side preview (left textarea + right marked.js render). Inside
   our split-pane editor we already provide a richer iframe preview on
   the right, so the inline preview column is redundant and visually
   noisy — collapse the panel to a single column and hide the inline
   preview side and its "Markdown Editor / Preview" mini-labels.

   NOTE: do NOT write component tags like the form-content-editor name
   in angle-bracket form inside this style block. Blade processes the
   entire block looking for component tags even within CSS comments,
   so a Blade x-component tag inside a CSS comment gets compiled to
   PHP and breaks the surrounding template (parse error: unexpected
   EOF expecting endif). Always refer to the component by its plain
   name in CSS comments. */
.dixlase-blog-editor [x-show="editorType === 'markdown'"] > .grid.grid-cols-1 {
    grid-template-columns: 1fr !important;
}
.dixlase-blog-editor [x-show="editorType === 'markdown'"] > .grid.grid-cols-1 > div:nth-child(2) {
    display: none;
}
.dixlase-blog-editor [x-show="editorType === 'markdown'"] > .grid.grid-cols-1 > div:nth-child(1) > div:first-child {
    display: none;
}
</style>
@endpush

{{-- blogPostEditor reads data-blog-editor-config, so x-data stays a bare name (CSP-ready Alpine). --}}
<div x-data="blogPostEditor"
     data-blog-editor-config="{{ json_encode([
         'csrfToken' => csrf_token(),
         'serverPreviewUrl' => route('dixlase-blog::admin.blog.preview'),
         'previewFrameUrl' => $previewFrameUrl,
         'editorType' => $editorTypeSlug,
         'initialContent' => old('content', $post->content ?? ''),
         'initialTitle' => old('title', $post->title ?? ''),
     ]) }}"
     class="dixlase-blog-editor min-w-0">

    {{-- Preview toggle (centre column, top) --}}
    <div class="flex flex-wrap items-center gap-3 mb-4 text-xs">
        <button type="button" @click="togglePreview()"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border transition-colors"
            :class="previewVisible
                ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-700'
                : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-600 hover:text-gray-700 dark:hover:text-gray-300'">
            <i class="fas" :class="previewVisible ? 'fa-eye' : 'fa-eye-slash'"></i>
            <span x-text="previewVisible
                ? '{{ __('dixlase-blog::admin.post_form.preview_hide') }}'
                : '{{ __('dixlase-blog::admin.post_form.preview_show') }}'"></span>
        </button>
    </div>

    {{-- Split-pane container --}}
    <div x-ref="splitContainer"
         class="flex gap-4 overflow-hidden"
         :class="[
             isHorizontal ? 'flex-row' : 'flex-col',
             (isDragging || isResizingPreview) ? 'select-none' : '',
         ]">

        {{-- Editor pane (no white card wrapper — matches FrontPage layout) --}}
        <div x-ref="editorPane"
             class="w-full min-w-0 space-y-4"
             :class="isHorizontal && previewVisible ? 'overflow-y-auto' : ''"
             :style="isHorizontal && previewVisible ? { width: (splitRatio * 100) + '%', maxHeight: 'calc(100vh - 160px)' } : {}">

            <x-form-content-editor
                storageType="database"
                :editorType="$editorTypeSlug"
                :content="old('content', $post->content ?? '')"
                :showStorageSelector="false"
                :showEditorSelector="!$isEditMode"
                contentFieldName="content"
                editorFieldName="editor_type"
                :editorIcons="$editorIcons"
                :editorTranslations="$editorTranslations"
            />
            <x-form-error name="content" />
        </div>

        {{-- Preview pane (iframe + device toggles + drag divider) --}}
        @include('components.content-editor.preview-pane')
    </div>
</div>

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
