{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Right-sidebar partial for the post form. Section order matches the
operator-requested layout:

  1. Preview         (button — opens saved public URL in a new tab;
                      the live in-page preview lives in the centre)
  2. Author info     (edit only) author / created / updated —
                      moved here per operator request so the
                      provenance fields sit close to the Preview
                      action they relate to
  3. Meta info       (edit only) editor type badge + language code —
                      mirrors FrontPage's locked-meta panel
  4. Title / Slug / Excerpt
  5. Categories      (multi) / Tags (#tag space-confirmed)
  6. Featured image  via core <x-media.picker aspectRatio="ogp">
  7. Revisions       (edit only) link to history
  8. Publish         (Status / Publish at / Close at — bottom)

Editor type rationale: once a post is saved, switching the editor type
loses content fidelity (Markdown ↔ HTML round-trip is lossy), so the
editor selector is hidden in edit mode (see _form.blade.php). The
locked badge in section 3 surfaces "what editor this post is in" so
the operator does not lose track.

Title moved out of the centre column on operator request — the centre
is now the body editor only. The Title field stays required so server-
side validation continues to enforce it.
--}}

@php
    // The publish / close fields are datetime-local: a wall clock with no
    // offset. Dixlase stores UTC and shows the display timezone, so the
    // fields are rendered and read in that timezone, and named with it.
    $displayTimezone = \Plugins\DixlaseBlog\App\Support\DisplayTimezoneDatetime::zone();

    $statusOptions = [
        'draft' => \App\Enums\ContentStatus::DRAFT->label(),
        'published' => \App\Enums\ContentStatus::PUBLISHED->label(),
        'scheduled' => \App\Enums\ContentStatus::SCHEDULED->label(),
    ];

    // The sidebar Preview button posts the form, saved or not, and opens
    // the post in the theme's full layout in a new tab (an admin route, so
    // the admin login applies), vs. the editor's split-pane preview which
    // shows the body alone in a stripped-down iframe shell.
    $postPublicUrl = $isEditMode ? route('dixlase-blog::front.show', ['slug' => $post->getOriginal('slug')]) : null;

    $formPreviewUrl = route('dixlase-blog::admin.blog.previewForm', $isEditMode ? ['post' => $post->id] : []);

    // Resolve editor type once for the meta-info badge so the badge
    // reads the same enum the form-content-editor uses to drive content
    // rendering. tryFromSlug() handles the form-submitted-string case.
    $editorTypeEnum = $post->editor_type instanceof \App\Enums\ContentEditorType
        ? $post->editor_type
        : \App\Enums\ContentEditorType::tryFromSlug($editorTypeSlug ?? 'markdown');

    // Named in the admin's language, as the translate button names it.
    $langCode = $post->exists ? $post->sourceLocale() : ($langValue ?? config('app.locale'));
    $langName = \App\Helpers\LocaleHelper::getLocaleName($langCode, false);
@endphp

<x-admin.right-sidebar
    :openLabel="__('dixlase-blog::admin.post_form.sidebar_open')"
    :closeLabel="__('dixlase-blog::admin.post_form.sidebar_close')"
>

    {{-- ===== 1. Preview ===== --}}
    <x-content-editor.new-tab-preview
        :url="$formPreviewUrl"
        icon="fas fa-eye"
        :label="__('dixlase-blog::admin.post_form.preview_button')"
        :help="__('dixlase-blog::admin.post_form.preview_help')"
    />

    {{-- ===== 2. Author info (edit only — sits between Preview and Meta info per operator request) ===== --}}
    @if ($isEditMode)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                {{ __('dixlase-blog::admin.post_form.author_info_section') }}
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
                {{-- The saved post's address. Readers get a 404 there until
                     the post is visible, so until then it opens the preview. --}}
                <div class="flex gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">{{ __('dixlase-blog::admin.post.url') }}:</dt>
                    <dd class="min-w-0 text-gray-700 dark:text-gray-300">
                        @if ($post->isCurrentlyVisible())
                            <a href="{{ $postPublicUrl }}" target="_blank" rel="noopener"
                               class="break-all text-blue-600 hover:underline dark:text-blue-400">{{ $postPublicUrl }}</a>
                        @else
                            <a href="{{ route('dixlase-blog::admin.blog.previewPage', ['post' => $post->id]) }}" target="_blank" rel="noopener"
                               class="break-all text-blue-600 hover:underline dark:text-blue-400">{{ $postPublicUrl }}</a>
                            <span class="block text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.post.url_not_public') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    @endif

    {{-- ===== 3. Meta info (edit mode only) ===== --}}
    @if ($isEditMode && $editorTypeEnum)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                {{ __('dixlase-blog::admin.post_form.meta_info_section') }}
            </h3>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600"
                      title="{{ __('dixlase-blog::admin.post_form.meta_editor_locked_help') }}">
                    <i class="{{ $editorTypeEnum->iconClass() }}"></i>
                    {{ __($editorTypeEnum->translationKey()) }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                    <i class="fas fa-globe text-gray-500 dark:text-gray-400"></i>
                    {{ $langName }}
                </span>
            </div>
            <x-form-help-text :text="__('dixlase-blog::admin.post_form.meta_editor_locked_help')" />

            {{-- The translation plugin's button; it renders nothing for an unsaved
                 post. <x-dynamic-component> keeps this view compiling without
                 the plugin, and the view check keeps it rendering: a missing
                 component throws instead of rendering empty. --}}
            @if (view()->exists('dixlase-multilingual::components.translate-button'))
                <div class="mt-4">
                    <x-dynamic-component
                        component="dixlase-multilingual::translate-button"
                        type="dixlase-blog:post"
                        :id="$post->id"
                        :sourceLocale="$post->sourceLocale()"
                    />
                </div>
            @endif
        </div>
    @endif

    {{-- ===== 4. Title / Slug / Excerpt ===== --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
            {{ __('dixlase-blog::admin.post_form.meta_section') }}
        </h3>

        {{-- Source language: chosen once, on create, while the site serves
             more than one locale. A saved post shows it in the meta info
             above; other languages are added as translations. --}}
        @if (! $isEditMode && ! empty($multilingualEnabled))
            <div class="mb-4">
                <x-form-label :for="'lang'" :text="__('dixlase-blog::admin.post.lang_label')" />
                <x-form-select
                    id="lang"
                    name="lang"
                    :options="$languageOptions"
                    :value="$langValue"
                />
                <x-form-help-text :text="__('dixlase-blog::admin.post.lang_help')" />
                <x-form-error name="lang" />
            </div>
        @endif

        {{-- Title --}}
        {{-- blogPreviewTitle sends the title to the live preview. --}}
        <div x-data="blogPreviewTitle" data-input-id="title">
            <x-form-label :for="'title'" :text="__('dixlase-blog::admin.post.title_label')" :required="true" />
            <x-form-text
                id="title"
                name="title"
                :value="old('title', $post->title)"
                :placeholder="__('dixlase-blog::admin.post.title_placeholder')"
                required
            />
            <x-form-error name="title" />
        </div>

        {{-- Slug --}}
        <div class="mt-4">
            <x-form-label :for="'slug'" :text="__('dixlase-blog::admin.post.slug_label')" />
            <x-form-text
                id="slug"
                name="slug"
                :value="old('slug', $post->slug)"
                class="font-mono"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.post.slug_help')" />
            <x-form-error name="slug" />
        </div>

        {{-- Excerpt --}}
        <div class="mt-4">
            <x-form-label :for="'excerpt'" :text="__('dixlase-blog::admin.post.excerpt_label')" />
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

    {{-- ===== 5. Classification: categories + tags ===== --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
            {{ __('dixlase-blog::admin.post_form.classification_section') }}
        </h3>

        {{-- Categories (multi-select) --}}
        <div>
            <x-form-label :text="__('dixlase-blog::admin.post.categories_label')" />
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
                <div class="max-h-48 overflow-y-auto rounded border border-gray-200 dark:border-gray-700 p-2 space-y-1">
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

        {{-- Tags (#tag space-confirmed) --}}
        <div class="mt-4">
            <x-form-label :text="__('dixlase-blog::admin.post.tags_label')" />
            <div x-data="blogTagInput"
                 data-tags="{{ json_encode(array_values((array) old('tags', $tagNames))) }}"
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
    </div>

    {{-- ===== 6. Featured image ===== --}}
    {{-- blogFeaturedPreview sends the chosen image to the live preview. --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6"
         x-data="blogFeaturedPreview"
         data-input-id="featured_media_id">
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

    {{-- ===== 6a. Storage (advanced admin mode only, #193) =====
         Sends the choice to the content editor, which carries it in the
         form and shows the file paths. --}}
    @if (! empty($showStorageChoice))
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <x-content-editor.storage-info
            :storageOptions="$storageOptions"
            :storageType="$storageTypeValue"
            :showJsCss="true"
        />
    </div>
    @endif

    {{-- ===== 6b. SEO (DixlaseSEO enabled, blog integration on) =====
         x-dynamic-component keeps this view compiling without DixlaseSEO. --}}
    @if (! empty($seoMetaEnabled))
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <x-dynamic-component
                component="dixlase-seo::meta-fields"
                :description="old('seo_meta.description', $seoMeta?->description)"
                :ogpMediaId="old('seo_meta.ogp_media_id', $seoMeta?->ogpMediaId)"
                :ogpMedia="$seoOgpMedia"
            />
        </div>
    @endif

    {{-- ===== 7. Revisions (edit only — sits just above Publish per operator request) ===== --}}
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
    @endif

    {{-- ===== 8. Publish settings (bottom of the editable controls per operator request) ===== --}}
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
            {{ __('dixlase-blog::admin.post_form.publish_section') }}
        </h3>

        {{-- Status --}}
        <div>
            <x-form-label :for="'status'" :text="__('dixlase-blog::admin.post.status_label')" :required="true" />
            <x-form-select
                id="status"
                name="status"
                :options="$statusOptions"
                :value="$statusSlug"
            />
            <x-form-error name="status" />
        </div>

        {{-- Pinned: listed first on the blog index --}}
        <div class="mt-4">
            <x-form-toggle
                id="is_pinned"
                name="is_pinned"
                :label="__('dixlase-blog::admin.post.pinned_label')"
                :checked="(bool) old('is_pinned', $post->is_pinned ?? false)"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.post.pinned_help')" />
            <x-form-error name="is_pinned" />
        </div>

        {{-- Comments: the blog setting, or open / closed for this post --}}
        <div class="mt-4">
            <x-form-label :for="'comment_status'" :text="__('dixlase-blog::admin.post.comment_status_label')" />
            <x-form-select
                id="comment_status"
                name="comment_status"
                :options="$commentStatusOptions"
                :value="old('comment_status', $post->comment_status ?? 'inherit')"
            />
            <x-form-error name="comment_status" />
        </div>

        {{-- Publish at --}}
        <div class="mt-4">
            <x-form-label :for="'published_at'" :text="__('dixlase-blog::admin.post.published_at_label')" />
            <x-form-text
                id="published_at"
                name="published_at"
                type="datetime-local"
                :value="\Plugins\DixlaseBlog\App\Support\DisplayTimezoneDatetime::forInput(old('published_at', $post->published_at))"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.post.published_at_help', ['timezone' => $displayTimezone])" />
            <x-form-error name="published_at" />
        </div>

        {{-- Close at --}}
        <div class="mt-4">
            <x-form-label :for="'closed_at'" :text="__('dixlase-blog::admin.post.closed_at_label')" />
            <x-form-text
                id="closed_at"
                name="closed_at"
                type="datetime-local"
                :value="\Plugins\DixlaseBlog\App\Support\DisplayTimezoneDatetime::forInput(old('closed_at', $post->closed_at))"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.post.closed_at_help', ['timezone' => $displayTimezone])" />
            <x-form-error name="closed_at" />
        </div>
    </div>


</x-admin.right-sidebar>
