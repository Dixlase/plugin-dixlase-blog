{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as the parent file (see edit.blade.php).

Shared form partial used by both create and edit. The wrapping <form> tag
is rendered by the parent so the right-sidebar can sit inside the same
form element (and so the save button at the page level can submit it).
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

<div class="grid grid-cols-1 gap-6">
    {{-- ===== Main editor area ===== --}}
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">
            {{-- Title --}}
            <div>
                <x-form-label :for="'title'" :label="__('dixlase-blog::admin.post.title_label')" />
                <x-form-text
                    id="title"
                    name="title"
                    :value="old('title', $post->title)"
                    :placeholder="__('dixlase-blog::admin.post.title_placeholder')"
                    required
                />
                <x-form-error name="title" />
            </div>

            {{-- Content (shared content-editor component) --}}
            <div>
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

            {{-- Excerpt --}}
            <div>
                <x-form-label :for="'excerpt'" :label="__('dixlase-blog::admin.post.excerpt_label')" />
                <x-form-textarea
                    id="excerpt"
                    name="excerpt"
                    :value="old('excerpt', $post->excerpt)"
                    rows="3"
                    maxlength="500"
                />
                <x-form-help-text :text="__('dixlase-blog::admin.post.excerpt_help')" />
                <x-form-error name="excerpt" />
            </div>
        </div>
    </div>

    {{-- ===== Right sidebar ===== --}}
    @include('dixlase-blog::admin.posts.partials._right-sidebar', [
        'post' => $post,
        'isEditMode' => $isEditMode,
        'statusSlug' => $statusSlug,
        'categoryTree' => $categoryTree,
        'selectedCategoryIds' => $selectedCategoryIds,
        'tagNames' => $tagNames,
    ])
</div>
