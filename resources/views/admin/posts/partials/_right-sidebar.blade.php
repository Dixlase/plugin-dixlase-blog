{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Right-sidebar partial for the post form: status, slug, publish/close,
featured image (via core's <x-media.picker>), categories (multi),
tags (#tag space-confirmed via Alpine), revision link.
--}}

@php
    $statusOptions = [
        'draft' => __(\App\Enums\ContentStatus::DRAFT->translationKey()),
        'published' => __(\App\Enums\ContentStatus::PUBLISHED->translationKey()),
        'scheduled' => __(\App\Enums\ContentStatus::SCHEDULED->translationKey()),
    ];
@endphp

<x-admin.right-sidebar
    :openLabel="__('dixlase-blog::admin.post_form.sidebar_open')"
    :closeLabel="__('dixlase-blog::admin.post_form.sidebar_close')"
>

    {{-- ===== Publish ===== --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
            {{ __('dixlase-blog::admin.post_form.publish_section') }}
        </h3>

        {{-- Status --}}
        <div>
            <x-form-label :for="'status'" :label="__('dixlase-blog::admin.post.status_label')" />
            <x-form-select
                id="status"
                name="status"
                :options="$statusOptions"
                :value="$statusSlug"
            />
            <x-form-error name="status" />
        </div>

        {{-- Publish at --}}
        <div class="mt-4">
            <x-form-label :for="'published_at'" :label="__('dixlase-blog::admin.post.published_at_label')" />
            <x-form-text
                id="published_at"
                name="published_at"
                type="datetime-local"
                :value="old('published_at', $post->published_at?->format('Y-m-d\TH:i'))"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.post.published_at_help')" />
            <x-form-error name="published_at" />
        </div>

        {{-- Close at --}}
        <div class="mt-4">
            <x-form-label :for="'closed_at'" :label="__('dixlase-blog::admin.post.closed_at_label')" />
            <x-form-text
                id="closed_at"
                name="closed_at"
                type="datetime-local"
                :value="old('closed_at', $post->closed_at?->format('Y-m-d\TH:i'))"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.post.closed_at_help')" />
            <x-form-error name="closed_at" />
        </div>
    </div>

    {{-- ===== Slug ===== --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <x-form-label :for="'slug'" :label="__('dixlase-blog::admin.post.slug_label')" />
        <x-form-text
            id="slug"
            name="slug"
            :value="old('slug', $post->slug)"
            class="font-mono"
        />
        <x-form-help-text :text="__('dixlase-blog::admin.post.slug_help')" />
        <x-form-error name="slug" />
    </div>

    {{-- ===== Featured image ===== --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
            {{ __('dixlase-blog::admin.post_form.media_section') }}
        </h3>
        <x-media.picker
            name="featured_media_id"
            :value="old('featured_media_id', $post->featured_media_id)"
            :media="$post->featuredMedia ?? null"
            aspectRatio="ogp"
            :help="__('dixlase-blog::admin.post.featured_image_help')"
        />
        <x-form-error name="featured_media_id" />
    </div>

    {{-- ===== Categories (multi) ===== --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
            {{ __('dixlase-blog::admin.post.categories_label') }}
        </h3>
        @if ($categoryTree->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                {{ __('dixlase-blog::admin.post.no_categories') }}
            </p>
            <x-form-button
                type="link"
                :href="route('dixlase-blog::admin.blog.categories.index')"
                size="xs"
                variant="secondary"
                icon="fas fa-folder-plus"
            >
                {{ __('dixlase-blog::admin.category.new_button') }}
            </x-form-button>
        @else
            @php
                $oldCategoryIds = old('categories', $selectedCategoryIds);
                if (! is_array($oldCategoryIds)) {
                    $oldCategoryIds = [];
                }
                $oldCategoryIds = array_map('intval', $oldCategoryIds);
            @endphp
            <div class="max-h-64 overflow-y-auto rounded border border-gray-200 dark:border-gray-700 p-2 space-y-1">
                @foreach ($categoryTree as $category)
                    <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded px-1 py-0.5"
                           style="padding-left: {{ ($category->depth * 1.25) + 0.25 }}rem;">
                        <input type="checkbox"
                               name="categories[]"
                               value="{{ $category->id }}"
                               @checked(in_array((int) $category->id, $oldCategoryIds, true))
                               class="rounded border-gray-300 dark:border-gray-600 text-blue-600">
                        <span class="text-gray-700 dark:text-gray-300">{{ $category->label }}</span>
                    </label>
                @endforeach
            </div>
            <x-form-help-text :text="__('dixlase-blog::admin.post.categories_help')" />
        @endif
        <x-form-error name="categories" />
    </div>

    {{-- ===== Tags (#tag space-confirmed) ===== --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
            {{ __('dixlase-blog::admin.post.tags_label') }}
        </h3>
        <div x-data="blogTagInput({{ Js::from(old('tags', $tagNames)) }})"
             class="rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 flex flex-wrap gap-1 items-center min-h-10">
            <template x-for="(tag, index) in tags" :key="index">
                <span class="inline-flex items-center gap-1 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 px-2 py-0.5 text-xs">
                    <span x-text="'#' + tag"></span>
                    <input type="hidden" name="tags[]" :value="tag">
                    <button type="button"
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400"
                            @click="removeTag(index)">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </span>
            </template>
            <input type="text"
                   x-model="currentInput"
                   @keydown="onKeydown($event)"
                   @blur="onBlur()"
                   :placeholder="tags.length === 0 ? '{{ __('dixlase-blog::admin.post.tags_placeholder') }}' : ''"
                   class="flex-1 min-w-32 border-none focus:ring-0 bg-transparent text-sm">
        </div>
        <x-form-help-text :text="__('dixlase-blog::admin.post.tags_help')" />
        <x-form-error name="tags" />
    </div>

    {{-- ===== Revisions (edit only) ===== --}}
    @if ($isEditMode)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                {{ __('dixlase-blog::admin.post_form.revision_section') }}
            </h3>
            <x-form-button
                type="link"
                :href="route('dixlase-blog::admin.blog.revisions.index', ['post' => $post->id])"
                icon="fas fa-clock-rotate-left"
                size="sm"
                variant="secondary"
                class="w-full"
            >
                {{ __('dixlase-blog::admin.post_form.revision_link') }}
            </x-form-button>
            <x-form-help-text :text="__('dixlase-blog::admin.post_form.revision_help')" />
        </div>

        {{-- Meta info --}}
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                {{ __('dixlase-blog::admin.post_form.meta_section') }}
            </h3>
            <dl class="space-y-1 text-xs">
                <div class="flex gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">{{ __('dixlase-blog::admin.post.author') }}:</dt>
                    <dd class="text-gray-700 dark:text-gray-300">
                        {{ $post->author?->display_name ?? $post->author?->account_name ?? __('dixlase-blog::admin.post.author_unknown') }}
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">{{ __('dixlase-blog::admin.post.created_at') }}:</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $post->created_at?->format('Y-m-d H:i') }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">{{ __('dixlase-blog::admin.post.updated_at') }}:</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $post->updated_at?->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        </div>
    @endif

</x-admin.right-sidebar>
