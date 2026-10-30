{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Comment settings: moderation, limits, notification and personal data.
--}}

@extends('dixlase-blog::admin.settings._screen')

@section('fields')
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
@endsection
