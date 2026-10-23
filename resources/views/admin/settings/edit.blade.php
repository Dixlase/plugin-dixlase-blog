{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Blog settings. Each feature (listings, sidebar, sharing, comments) has
its own <section> in this one form.
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

                <div>
                    <x-form-label :text="__('dixlase-blog::admin.settings.index_layout_label')" :required="true" class="mb-2" />
                    <x-form-radio-card-group
                        name="index_layout"
                        :options="$indexLayoutOptions"
                        :value="old('index_layout', $blogSettings['index_layout'])"
                        :columns="2"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.index_layout_help')" />
                    <x-form-error name="index_layout" />
                </div>

                <div class="max-w-xs">
                    <x-form-label
                        for="index_full_posts_per_page"
                        :text="__('dixlase-blog::admin.settings.index_full_posts_per_page_label')"
                        :required="true"
                    />
                    <x-form-text
                        id="index_full_posts_per_page"
                        name="index_full_posts_per_page"
                        type="number"
                        :min="$indexFullPostsMin"
                        :max="$indexFullPostsMax"
                        :value="old('index_full_posts_per_page', $blogSettings['index_full_posts_per_page'])"
                        :required="true"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.index_full_posts_per_page_help', ['min' => $indexFullPostsMin, 'max' => $indexFullPostsMax])" />
                    <x-form-error name="index_full_posts_per_page" />
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

        <section class="mb-8">
            <h2 class="mb-4 text-xl font-semibold">{{ __('dixlase-blog::admin.settings.comments_heading') }}</h2>

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <x-form-toggle
                        id="comments_enabled"
                        name="comments_enabled"
                        :label="__('dixlase-blog::admin.settings.comments_enabled_label')"
                        :checked="(bool) old('comments_enabled', $blogSettings['comments_enabled'])"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.comments_enabled_help')" />
                    <x-form-error name="comments_enabled" />
                </div>

                <div>
                    <x-form-label :text="__('dixlase-blog::admin.settings.comment_moderation_label')" :required="true" class="mb-2" />
                    <x-form-radio-card-group
                        name="comment_moderation"
                        :options="$commentModerationOptions"
                        :value="old('comment_moderation', $blogSettings['comment_moderation'])"
                        :columns="2"
                    />
                    <x-form-error name="comment_moderation" />
                </div>

                <div class="max-w-xs">
                    <x-form-label
                        for="comments_close_after_days"
                        :text="__('dixlase-blog::admin.settings.comments_close_after_days_label')"
                        :required="true"
                    />
                    <x-form-text
                        id="comments_close_after_days"
                        name="comments_close_after_days"
                        type="number"
                        :min="0"
                        :max="$commentsCloseAfterDaysMax"
                        :value="old('comments_close_after_days', $blogSettings['comments_close_after_days'])"
                        :required="true"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.comments_close_after_days_help')" />
                    <x-form-error name="comments_close_after_days" />
                </div>

                <div class="max-w-xs">
                    <x-form-label
                        for="comment_link_limit"
                        :text="__('dixlase-blog::admin.settings.comment_link_limit_label')"
                        :required="true"
                    />
                    <x-form-text
                        id="comment_link_limit"
                        name="comment_link_limit"
                        type="number"
                        :min="0"
                        :max="$commentLinkLimitMax"
                        :value="old('comment_link_limit', $blogSettings['comment_link_limit'])"
                        :required="true"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.comment_link_limit_help')" />
                    <x-form-error name="comment_link_limit" />
                </div>

                {{-- The throttle on the comment form, per IP address. --}}
                <fieldset>
                    <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('dixlase-blog::admin.settings.comment_rate_legend') }}
                    </legend>
                    <div class="mt-2 flex flex-wrap gap-4">
                        <div class="w-40">
                            <x-form-label
                                for="comment_rate_decay_minutes"
                                :text="__('dixlase-blog::admin.settings.comment_rate_decay_minutes_label')"
                                :required="true"
                            />
                            <x-form-text
                                id="comment_rate_decay_minutes"
                                name="comment_rate_decay_minutes"
                                type="number"
                                :min="1"
                                :max="$commentRateDecayMinutesMax"
                                :value="old('comment_rate_decay_minutes', $blogSettings['comment_rate_decay_minutes'])"
                                :required="true"
                            />
                            <x-form-error name="comment_rate_decay_minutes" />
                        </div>
                        <div class="w-40">
                            <x-form-label
                                for="comment_rate_max_attempts"
                                :text="__('dixlase-blog::admin.settings.comment_rate_max_attempts_label')"
                                :required="true"
                            />
                            <x-form-text
                                id="comment_rate_max_attempts"
                                name="comment_rate_max_attempts"
                                type="number"
                                :min="1"
                                :max="$commentRateMaxAttemptsMax"
                                :value="old('comment_rate_max_attempts', $blogSettings['comment_rate_max_attempts'])"
                                :required="true"
                            />
                            <x-form-error name="comment_rate_max_attempts" />
                        </div>
                    </div>
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.comment_rate_help')" />
                </fieldset>

                <div>
                    <x-form-toggle
                        id="comment_notify"
                        name="comment_notify"
                        :label="__('dixlase-blog::admin.settings.comment_notify_label')"
                        :checked="(bool) old('comment_notify', $blogSettings['comment_notify'])"
                    />
                    <x-form-error name="comment_notify" />
                </div>

                <div class="max-w-xl">
                    <x-form-label for="comment_notify_email" :text="__('dixlase-blog::admin.settings.comment_notify_email_label')" />
                    <x-form-text
                        id="comment_notify_email"
                        name="comment_notify_email"
                        type="email"
                        :maxlength="255"
                        :placeholder="$siteAdminEmail"
                        :value="old('comment_notify_email', $blogSettings['comment_notify_email'])"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.comment_notify_email_help')" />
                    <x-form-error name="comment_notify_email" />
                </div>

                <div class="max-w-xs">
                    <x-form-label
                        for="comment_ip_retention_days"
                        :text="__('dixlase-blog::admin.settings.comment_ip_retention_days_label')"
                        :required="true"
                    />
                    <x-form-text
                        id="comment_ip_retention_days"
                        name="comment_ip_retention_days"
                        type="number"
                        :min="0"
                        :max="$commentIpRetentionDaysMax"
                        :value="old('comment_ip_retention_days', $blogSettings['comment_ip_retention_days'])"
                        :required="true"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.comment_ip_retention_days_help')" />
                    <x-form-error name="comment_ip_retention_days" />
                </div>

                <div class="max-w-xl">
                    <x-form-label for="privacy_policy_url" :text="__('dixlase-blog::admin.settings.privacy_policy_url_label')" />
                    <x-form-text
                        id="privacy_policy_url"
                        name="privacy_policy_url"
                        type="url"
                        :maxlength="2048"
                        :value="old('privacy_policy_url', $blogSettings['privacy_policy_url'])"
                    />
                    @if ($providedPrivacyUrl)
                        <x-form-help-text :text="__('dixlase-blog::admin.settings.privacy_policy_url_provided', ['url' => $providedPrivacyUrl])" />
                    @else
                        <x-form-help-text :text="__('dixlase-blog::admin.settings.privacy_policy_url_help')" />
                    @endif
                    <x-form-error name="privacy_policy_url" />
                </div>
            </div>
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
