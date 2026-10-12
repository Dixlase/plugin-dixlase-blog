{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Blog settings. Each feature (listings, sidebar, and later comments and
sharing) has its own <section> in this one form.
--}}

@extends('layouts.admin')

@section('content')
    <form id="blog-settings-form" action="{{ route('dixlase-blog::admin.blog.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <section class="mb-8">
            <h2 class="mb-4 text-xl font-semibold">{{ __('dixlase-blog::admin.settings.listing_heading') }}</h2>

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
            </div>
        </section>

        <section class="mb-8">
            <h2 class="mb-4 text-xl font-semibold">{{ __('dixlase-blog::admin.settings.sidebar_heading') }}</h2>

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <x-form-toggle
                        id="sidebar_enabled"
                        name="sidebar_enabled"
                        :label="__('dixlase-blog::admin.settings.sidebar_enabled_label')"
                        :checked="(bool) old('sidebar_enabled', $blogSettings['sidebar_enabled'])"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.sidebar_enabled_help')" />
                    <x-form-error name="sidebar_enabled" />
                </div>

                <div>
                    <x-form-label :text="__('dixlase-blog::admin.settings.sidebar_position_label')" :required="true" class="mb-2" />
                    <x-form-radio-card-group
                        name="sidebar_position"
                        :options="$sidebarPositionOptions"
                        :value="old('sidebar_position', $blogSettings['sidebar_position'])"
                        :columns="2"
                    />
                    <x-form-error name="sidebar_position" />
                </div>

                <div>
                    <x-form-label :text="__('dixlase-blog::admin.settings.sidebar_widgets_label')" class="mb-2" />
                    <div x-data="blogSidebarWidgets">
                        <ul x-ref="list" class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            @foreach ($sidebarWidgets as $widget)
                                <li class="flex items-center gap-3 bg-white px-3 py-2 dark:bg-gray-800">
                                    <span data-drag-handle class="cursor-move text-gray-400" title="{{ __('dixlase-blog::admin.settings.sidebar_widget_drag') }}">
                                        <i class="fas fa-grip-vertical" aria-hidden="true"></i>
                                    </span>
                                    <input type="hidden" name="sidebar_widgets[]" value="{{ $widget['key'] }}">
                                    <x-form-toggle
                                        :id="'sidebar_widget_'.$widget['key']"
                                        :name="'sidebar_widget_enabled['.$widget['key'].']'"
                                        :label="$widget['label']"
                                        :checked="(bool) old('sidebar_widget_enabled.'.$widget['key'], $widget['enabled'])"
                                    />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.sidebar_widgets_help')" />
                    <x-form-error name="sidebar_widgets" />
                </div>

                <div class="max-w-xs">
                    <x-form-label
                        for="sidebar_recent_count"
                        :text="__('dixlase-blog::admin.settings.sidebar_recent_count_label')"
                        :required="true"
                    />
                    <x-form-text
                        id="sidebar_recent_count"
                        name="sidebar_recent_count"
                        type="number"
                        :min="$recentPostsMin"
                        :max="$recentPostsMax"
                        :value="old('sidebar_recent_count', $blogSettings['sidebar_recent_count'])"
                        :required="true"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.sidebar_recent_count_help', ['min' => $recentPostsMin, 'max' => $recentPostsMax])" />
                    <x-form-error name="sidebar_recent_count" />
                </div>
            </div>
        </section>

        <section class="mb-8">
            <h2 class="mb-4 text-xl font-semibold">{{ __('dixlase-blog::admin.settings.share_heading') }}</h2>
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('dixlase-blog::admin.settings.share_help') }}</p>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($shareServices as $service)
                    <x-form-toggle
                        :id="'share_service_'.$service['key']"
                        :name="'share_services['.$service['key'].']'"
                        :label="$service['label']"
                        :checked="(bool) old('share_services.'.$service['key'], $service['enabled'])"
                    />
                @endforeach
            </div>
            <x-form-error name="share_services" />
        </section>
    </form>
@endsection

@section('save')
    <x-admin.save-button
        id_confirmation="confirmBlogSettingsModal"
        :label="__('common.save')"
        :title="__('dixlase-blog::admin.settings.confirm_title')"
        :message="__('dixlase-blog::admin.settings.confirm_message')"
        :confirm_label="__('common.save')"
        :cancel_label="__('common.cancel')"
        form="blog-settings-form"
    />
@endsection
