{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Listing settings: the blog's URL, posts per page, the top page layout and
what the cards and the latest posts show.
--}}

@extends('dixlase-blog::admin.settings._screen')

@section('fields')
    <div class="grid grid-cols-1 gap-6">
        <div>
            <x-form-label
                for="route_slug"
                :text="__('dixlase-blog::admin.settings.route_slug_label')"
                :required="true"
            />
            <div class="mt-1 flex max-w-xl items-center">
                <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400">
                    {{ $siteUrl }}/
                </span>
                <x-form-text
                    id="route_slug"
                    name="route_slug"
                    :maxlength="$routeSlugMax"
                    :value="old('route_slug', $blogSettings['route_slug'])"
                    :required="true"
                    class="rounded-l-none font-mono"
                />
            </div>
            <x-form-help-text :text="__('dixlase-blog::admin.settings.route_slug_help')" />
            <x-form-error name="route_slug" />
        </div>

        <div class="max-w-xs">
            <x-form-label
                for="posts_per_page"
                :text="__('dixlase-blog::admin.settings.posts_per_page_label')"
                :required="true"
            />
            <x-form-text
                id="posts_per_page"
                name="posts_per_page"
                type="number"
                :min="$postsPerPageMin"
                :max="$postsPerPageMax"
                :value="old('posts_per_page', $blogSettings['posts_per_page'])"
                :required="true"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.settings.posts_per_page_help', ['min' => $postsPerPageMin, 'max' => $postsPerPageMax])" />
            <x-form-error name="posts_per_page" />
        </div>

        {{-- The text choice belongs to the latest posts only (blogListingLayout). --}}
        <div x-data="blogListingLayout" data-layout="{{ old('index_layout', $blogSettings['index_layout']) }}" class="grid grid-cols-1 gap-6">
            <div>
                <x-form-label :text="__('dixlase-blog::admin.settings.index_layout_label')" :required="true" class="mb-2" />
                <x-form-radio-card-group
                    name="index_layout"
                    :options="$indexLayoutOptions"
                    :value="old('index_layout', $blogSettings['index_layout'])"
                    xModel="layout"
                    :columns="2"
                />
                <x-form-help-text :text="__('dixlase-blog::admin.settings.index_layout_help')" />
                <x-form-error name="index_layout" />
            </div>

            <div x-show="latest" x-cloak>
                <x-form-label :text="__('dixlase-blog::admin.settings.latest_body_label')" :required="true" class="mb-2" />
                <x-form-radio-card-group
                    name="latest_body"
                    :options="$latestBodyOptions"
                    :value="old('latest_body', $blogSettings['latest_body'])"
                    :columns="2"
                />
                <x-form-help-text :text="__('dixlase-blog::admin.settings.latest_body_help')" />
                <x-form-error name="latest_body" />
            </div>
        </div>

        <div>
            <x-form-label :text="__('dixlase-blog::admin.settings.post_fields_label')" class="mb-2" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($postFields as $field)
                    <x-form-toggle
                        :id="'post_field_'.$field['key']"
                        :name="'post_fields['.$field['key'].']'"
                        :label="$field['label']"
                        :checked="(bool) old('post_fields.'.$field['key'], $field['enabled'])"
                    />
                @endforeach
            </div>
            <x-form-help-text :text="__('dixlase-blog::admin.settings.post_fields_help')" />
            <x-form-error name="post_fields" />
        </div>

        <div>
            <x-form-toggle
                id="card_excerpt"
                name="card_excerpt"
                :label="__('dixlase-blog::admin.settings.card_excerpt_label')"
                :checked="(bool) old('card_excerpt', $blogSettings['card_excerpt'])"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.settings.card_excerpt_help')" />
            <x-form-error name="card_excerpt" />
        </div>

        <div>
            <x-form-label :text="__('dixlase-blog::admin.settings.card_columns_label')" :required="true" class="mb-2" />
            <x-form-radio-card-group
                name="card_columns"
                :options="$cardColumnsOptions"
                :value="old('card_columns', $blogSettings['card_columns'])"
                :columns="2"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.settings.card_columns_help')" />
            <x-form-error name="card_columns" />
        </div>
    </div>
@endsection
