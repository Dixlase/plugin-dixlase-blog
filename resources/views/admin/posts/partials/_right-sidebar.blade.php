{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Right-sidebar partial for the post form. Every section but the preview
button is a core <x-content-editor.sidebar-section>, which opens and
closes, shows a summary while closed, opens when one of its fields has
an error and remembers the viewer's choice (#201):

  -  Preview          (always shown; opens the form in a new tab)
  1. Title / slug / excerpt              open
  2. Classification   categories, tags   closed
  3. Featured image                      closed
  4. SEO              (SEO integration)  closed
  5. Language and translation
                      (multilingual, or a translate button)    closed
  6. Post info        author, dates, URL, editor type, storage,
                      revisions link    closed
  7. Publish settings (bottom, per operator request)            open

Read-only facts (editor type) sit in post info and the language
actions in their own section, so no section mixes the two.

Editor type rationale: once a post is saved, switching the editor type
loses content fidelity (Markdown <-> HTML round-trip is lossy), so the
editor selector is hidden in edit mode (see _form.blade.php). The
locked badge in post info surfaces "what editor this post is in".

Title moved out of the centre column on operator request -- the centre
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

    // The translation plugin's button; it renders nothing for an unsaved
    // post. <x-dynamic-component> keeps this view compiling without the
    // plugin, and the view check keeps it rendering: a missing component
    // throws instead of rendering empty.
    $hasTranslateButton = $isEditMode && view()->exists('dixlase-multilingual::components.translate-button');
    $showLanguageSection = ! empty($multilingualEnabled) || $hasTranslateButton;
    $showPostInfo = $isEditMode || ! empty($showStorageChoice);

    // Summaries shown next to a closed section's title. They describe the
    // form as it was loaded (or as it came back from validation).
    $currentStatus = (string) old('status', $statusSlug ?? 'draft');
    $publishedAtValue = old('published_at', $post->published_at);
    $publishSummary = $statusOptions[$currentStatus] ?? '';
    if ($currentStatus === 'scheduled' && filled($publishedAtValue)) {
        $publishSummary = __('dixlase-blog::admin.post_form.summary_scheduled', [
            'date' => str_replace('T', ' ', (string) \Plugins\DixlaseBlog\App\Support\DisplayTimezoneDatetime::forInput($publishedAtValue)),
        ]);
    }

    $summaryCategoryIds = old('categories', $selectedCategoryIds ?? []);
    $summaryTags = old('tags', $tagNames ?? []);
    $classificationSummary = __('dixlase-blog::admin.post_form.summary_classification', [
        'categories' => is_array($summaryCategoryIds) ? count($summaryCategoryIds) : 0,
        'tags' => is_array($summaryTags) ? count(array_filter($summaryTags, 'filled')) : 0,
    ]);

    $featuredSummary = filled(old('featured_media_id', $post->featured_media_id))
        ? __('dixlase-blog::admin.post_form.summary_set')
        : __('dixlase-blog::admin.post_form.summary_none');

    $seoSummary = filled(old('seo_meta.description', $seoMeta?->description ?? null))
        ? __('dixlase-blog::admin.post_form.summary_set')
        : __('dixlase-blog::admin.post_form.summary_none');

    $postInfoSummary = collect([
        $isEditMode && $editorTypeEnum ? __($editorTypeEnum->translationKey()) : null,
        ! empty($showStorageChoice) ? ($storageOptions[$storageTypeValue]['label'] ?? null) : null,
    ])->filter()->implode(' · ');
@endphp

<x-admin.right-sidebar
    :openLabel="__('dixlase-blog::admin.post_form.sidebar_open')"
    :closeLabel="__('dixlase-blog::admin.post_form.sidebar_close')"
>

    {{-- Preview: always shown, outside the sections --}}
    <x-content-editor.new-tab-preview
        :url="$formPreviewUrl"
        icon="fas fa-eye"
        :label="__('dixlase-blog::admin.post_form.preview_button')"
        :help="__('dixlase-blog::admin.post_form.preview_help')"
    />

    {{-- ===== 1. Title / Slug / Excerpt ===== --}}
    <x-content-editor.sidebar-section
        :title="__('dixlase-blog::admin.post_form.meta_section')"
        icon="fas fa-heading"
        :open="true"
        :summary="\Illuminate\Support\Str::limit((string) old('title', $post->title), 40)"
        :errorFields="['title', 'slug', 'excerpt']"
        rememberKey="dixlase-blog.post.title"
    >
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
    </x-content-editor.sidebar-section>

    {{-- ===== 2. Classification: categories + tags ===== --}}
    <x-content-editor.sidebar-section
        :title="__('dixlase-blog::admin.post_form.classification_section')"
        icon="fas fa-folder-tree"
        :summary="$classificationSummary"
        :errorFields="['categories', 'categories.*', 'tags', 'tags.*']"
        rememberKey="dixlase-blog.post.classification"
    >
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

        {{-- Tags: core's tag input suggests the blog's tags as they are
             typed (#205). Space or Enter adds what was typed, a leading
             "#" is dropped, and each tag posts as tags[], as before. --}}
        <div class="mt-4">
            <x-form-label :for="'postTags-field'" :text="__('dixlase-blog::admin.post.tags_label')" />
            <x-form-tag-input
                id="postTags"
                name="tags[]"
                :tagsUrl="route('dixlase-blog::admin.blog.tags.list')"
                :selected="array_values((array) old('tags', $tagNames))"
                :maxlength="100"
                :commitTyped="true"
                :stripHash="true"
                :placeholder="__('dixlase-blog::admin.post.tags_placeholder')"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.post.tags_help')" />
            <x-form-error name="tags" />
        </div>
    </x-content-editor.sidebar-section>

    {{-- ===== 3. Featured image ===== --}}
    <x-content-editor.sidebar-section
        :title="__('dixlase-blog::admin.post_form.media_section')"
        icon="fas fa-image"
        :summary="$featuredSummary"
        :errorFields="['featured_media_id']"
        rememberKey="dixlase-blog.post.featured"
    >
        {{-- blogFeaturedPreview sends the chosen image to the live preview. --}}
        <div x-data="blogFeaturedPreview" data-input-id="featured_media_id">
            <x-media.picker
                name="featured_media_id"
                :value="old('featured_media_id', $post->featured_media_id)"
                :media="$post->featuredMedia ?? null"
                aspectRatio="ogp"
                :help="__('dixlase-blog::admin.post.featured_image_help')"
            />
            <x-form-error name="featured_media_id" />
        </div>
    </x-content-editor.sidebar-section>

    {{-- ===== 4. SEO (DixlaseSEO enabled, blog integration on) =====
         x-dynamic-component keeps this view compiling without DixlaseSEO. --}}
    @if (! empty($seoMetaEnabled))
        <x-content-editor.sidebar-section
            :title="__('dixlase-blog::admin.post_form.seo_section')"
            icon="fas fa-magnifying-glass"
            :summary="$seoSummary"
            :errorFields="['seo_meta', 'seo_meta.*']"
            rememberKey="dixlase-blog.post.seo"
        >
            <x-dynamic-component
                component="dixlase-seo::meta-fields"
                :description="old('seo_meta.description', $seoMeta?->description)"
                :ogpMediaId="old('seo_meta.ogp_media_id', $seoMeta?->ogpMediaId)"
                :ogpMedia="$seoOgpMedia"
            />
        </x-content-editor.sidebar-section>
    @endif

    {{-- ===== 5. Language and translation ===== --}}
    @if ($showLanguageSection)
        <x-content-editor.sidebar-section
            :title="__('dixlase-blog::admin.post_form.language_section')"
            icon="fas fa-language"
            :summary="$langName"
            :errorFields="['lang', 'confirm_lang_change']"
            rememberKey="dixlase-blog.post.language"
        >
            {{-- Source language, while the site serves more than one locale.
                 A saved post can change it: its text stays, and a translation
                 filed under the new language moves to the old one. Choosing a
                 language the post has no translation in leaves the old one with
                 no text of its own, so blogSourceLanguage asks to confirm it,
                 and the update request refuses it unconfirmed (#133). --}}
            @if (! empty($multilingualEnabled))
                <div class="mb-4"
                     @if ($isEditMode)
                         x-data="blogSourceLanguage"
                         data-current="{{ $post->sourceLocale() }}"
                         data-translated="{{ implode(',', $translatedLocales ?? []) }}"
                         @change="pick($event)"
                     @endif
                >
                    <x-form-label :for="'lang'" :text="__('dixlase-blog::admin.post.lang_label')" />
                    <x-form-select
                        id="lang"
                        name="lang"
                        :options="$languageOptions"
                        :value="$langValue"
                    />
                    <x-form-help-text :text="__($isEditMode ? 'dixlase-blog::admin.post.lang_change_help' : 'dixlase-blog::admin.post.lang_help')" />
                    <x-form-error name="lang" />
                    @if ($isEditMode)
                        <div class="mt-2" x-show="needsConfirmation" x-cloak>
                            <x-form-toggle
                                id="confirm_lang_change"
                                name="confirm_lang_change"
                                :label="__('dixlase-blog::admin.post.lang_change_confirm', ['old' => \App\Helpers\LocaleHelper::getLocaleName($post->sourceLocale(), false)])"
                                :checked="(bool) old('confirm_lang_change', false)"
                            />
                        </div>
                    @endif
                </div>
            @endif

            @if ($hasTranslateButton)
                <div class="mt-4">
                    <x-dynamic-component
                        component="dixlase-multilingual::translate-button"
                        type="dixlase-blog:post"
                        :id="$post->id"
                        :sourceLocale="$post->sourceLocale()"
                    />
                </div>
            @endif
        </x-content-editor.sidebar-section>
    @endif

    {{-- ===== 6. Post info: what the post is, and its history ===== --}}
    @if ($showPostInfo)
        <x-content-editor.sidebar-section
            :title="__('dixlase-blog::admin.post_form.post_info_section')"
            icon="fas fa-circle-info"
            :summary="$postInfoSummary"
            :errorFields="['storage_type']"
            rememberKey="dixlase-blog.post.info"
        >
            @if ($isEditMode)
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

                @if ($editorTypeEnum)
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600 text-xs"
                              title="{{ __('dixlase-blog::admin.post_form.meta_editor_locked_help') }}">
                            <i class="{{ $editorTypeEnum->iconClass() }}"></i>
                            {{ __($editorTypeEnum->translationKey()) }}
                        </span>
                        <x-form-help-text :text="__('dixlase-blog::admin.post_form.meta_editor_locked_help')" />
                    </div>
                @endif
            @endif

            {{-- Storage (advanced admin mode only, #193). Sends the choice to
                 the content editor, which carries it in the form and shows
                 the file paths. --}}
            @if (! empty($showStorageChoice))
                <div class="{{ $isEditMode ? 'mt-4' : '' }}">
                    <x-content-editor.storage-info
                        :storageOptions="$storageOptions"
                        :storageType="$storageTypeValue"
                        :showJsCss="true"
                    />
                </div>
            @endif

            @if ($isEditMode)
                <div class="mt-4">
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
        </x-content-editor.sidebar-section>
    @endif

    {{-- ===== 7. Publish settings (bottom, per operator request) ===== --}}
    <x-content-editor.sidebar-section
        :title="__('dixlase-blog::admin.post_form.publish_section')"
        icon="fas fa-calendar-check"
        :open="true"
        :summary="$publishSummary"
        :errorFields="['status', 'is_pinned', 'comment_status', 'published_at', 'closed_at']"
        rememberKey="dixlase-blog.post.publish"
    >
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
    </x-content-editor.sidebar-section>

</x-admin.right-sidebar>
