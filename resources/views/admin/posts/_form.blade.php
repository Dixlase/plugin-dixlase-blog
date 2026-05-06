{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Shared form partial used by both create and edit. The wrapping <form>
tag is rendered by the parent so the right-sidebar can sit inside the
same form element (and so the save button at the page level can submit
it).

Layout: the centre column hosts only the content editor. Title, slug,
excerpt, classification, featured image, and publish settings all live
in the right sidebar — this matches the user's preferred layout where
the bulk of the page is the body editor and metadata sits in a
collapsible side panel.

The earlier draft wrapped centre + sidebar in a single
<div class="grid grid-cols-1">. That extra wrapper caused the centre
column to ignore the right sidebar's reserved width (the sidebar uses
position: fixed and the grid offered no padding-right), so the editor
overlapped the sidebar at common breakpoints. Mirror DixlaseLegal's
edit.blade.php structure: keep centre as a sibling of the sidebar
include, no wrapping grid.
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
@endphp

@csrf
@if ($isEditMode)
    @method('PUT')
@endif

{{-- ===== Centre column: content editor only ===== --}}
<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <x-form-content-editor
            storageType="database"
            :editorType="$editorTypeSlug"
            :content="old('content', $post->content ?? '')"
            :showStorageSelector="false"
            :showEditorSelector="true"
            contentFieldName="content"
            editorFieldName="editor_type"
            :editorIcons="$editorIcons"
            :editorTranslations="$editorTranslations"
        />
        <x-form-error name="content" />
    </div>
</div>

{{-- ===== Right sidebar: title / slug / excerpt / classification / media / publish ===== --}}
@include('dixlase-blog::admin.posts.partials._right-sidebar', [
    'post' => $post,
    'isEditMode' => $isEditMode,
    'statusSlug' => $statusSlug,
    'categoryTree' => $categoryTree,
    'selectedCategoryIds' => $selectedCategoryIds,
    'tagNames' => $tagNames,
])
