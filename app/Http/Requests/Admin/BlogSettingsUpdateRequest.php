<?php

/**
 * This file is part of Dixlase Blog.
 *
 * Copyright (C) 2026 exc-D inc.
 * https://exc-d.com
 *
 * Dixlase Blog is dual-licensed. You may use this file under either:
 *
 *   (a) the GNU General Public License version 3 or later, as published
 *       by the Free Software Foundation; or
 *
 *   (b) a commercial license agreement obtained from exc-D inc.
 *
 * Unless you have entered into a commercial license agreement, this
 * file is governed by the GPL terms below.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */


declare(strict_types=1);

namespace Plugins\DixlaseBlog\App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\LocaleHelper;
use App\Rules\UniqueRouteSlug;
use Illuminate\Validation\Rule;
use Plugins\DixlaseBlog\App\Providers\DixlaseBlogServiceProvider;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

class BlogSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Each section is checked when it is sent; the screen sends all.
            'route_slug' => [
                'sometimes',
                'required',
                'string',
                'max:'.BlogSettings::ROUTE_SLUG_MAX,
                'regex:'.BlogSettings::ROUTE_SLUG_PATTERN,
                // Another plugin's prefix, the admin URL or a reserved word.
                UniqueRouteSlug::for(DixlaseBlogServiceProvider::ROUTE_SLUG_OWNER),
                // Locale codes are the first segment of localized URLs.
                Rule::notIn(LocaleHelper::supportedLocales()),
            ],
            'posts_per_page' => [
                'sometimes',
                'required',
                'integer',
                'min:'.BlogSettings::POSTS_PER_PAGE_MIN,
                'max:'.BlogSettings::POSTS_PER_PAGE_MAX,
            ],
            'index_layout' => ['sometimes', 'required', Rule::in(BlogSettings::INDEX_LAYOUTS)],
            'sidebar_enabled' => ['sometimes', 'required', 'boolean'],
            'sidebar_position' => ['sometimes', 'required', Rule::in(BlogSettings::SIDEBAR_POSITIONS)],
            // The widget rows in their order on the form, and which are on.
            'sidebar_widgets' => ['sometimes', 'required', 'array'],
            'sidebar_widgets.*' => ['distinct', Rule::in(BlogSettings::SIDEBAR_WIDGETS)],
            'sidebar_widget_enabled' => ['array'],
            'sidebar_widget_enabled.*' => ['boolean'],
            // One switch per service, keyed by service.
            'share_services' => ['sometimes', 'array'],
            'share_services.*' => ['boolean'],
            'sidebar_recent_count' => [
                'sometimes',
                'required',
                'integer',
                'min:'.BlogSettings::RECENT_POSTS_MIN,
                'max:'.BlogSettings::RECENT_POSTS_MAX,
            ],
            'comments_enabled' => ['sometimes', 'required', 'boolean'],
            'comment_moderation' => ['sometimes', 'required', Rule::in(BlogSettings::COMMENT_MODERATION)],
            'comments_close_after_days' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:'.BlogSettings::COMMENTS_CLOSE_AFTER_DAYS_MAX,
            ],
            'comment_link_limit' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:'.BlogSettings::COMMENT_LINK_LIMIT_MAX,
            ],
            'comment_rate_max_attempts' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:'.BlogSettings::COMMENT_RATE_MAX_ATTEMPTS_MAX,
            ],
            'comment_rate_decay_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:'.BlogSettings::COMMENT_RATE_DECAY_MINUTES_MAX,
            ],
            'comment_notify' => ['sometimes', 'required', 'boolean'],
            'comment_notify_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'comment_ip_retention_days' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:'.BlogSettings::COMMENT_IP_RETENTION_DAYS_MAX,
            ],
            'privacy_policy_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'route_slug.regex' => __('dixlase-blog::admin.settings.route_slug_format'),
            'route_slug.not_in' => __('dixlase-blog::admin.settings.route_slug_locale'),
        ];
    }

    /**
     * The validated input as BlogSettings stores it: the widget order and
     * switches become one list.
     *
     * @return array<string, mixed>
     */
    public function settingsValues(): array
    {
        $data = $this->validated();
        $enabled = $data['sidebar_widget_enabled'] ?? [];

        if (isset($data['sidebar_widgets'])) {
            $data['sidebar_widgets'] = array_map(
                fn (string $key) => ['key' => $key, 'enabled' => (bool) ($enabled[$key] ?? false)],
                $data['sidebar_widgets'],
            );
        }
        unset($data['sidebar_widget_enabled']);

        if (isset($data['share_services'])) {
            $switches = $data['share_services'];
            $data['share_services'] = array_values(array_filter(
                BlogSettings::SHARE_SERVICES,
                fn (string $key) => (bool) ($switches[$key] ?? false),
            ));
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'posts_per_page' => __('dixlase-blog::admin.settings.posts_per_page_label'),
            'index_layout' => __('dixlase-blog::admin.settings.index_layout_label'),
            'route_slug' => __('dixlase-blog::admin.settings.route_slug_label'),
            'sidebar_position' => __('dixlase-blog::admin.settings.sidebar_position_label'),
            'sidebar_recent_count' => __('dixlase-blog::admin.settings.sidebar_recent_count_label'),
            'comment_moderation' => __('dixlase-blog::admin.settings.comment_moderation_label'),
            'comments_close_after_days' => __('dixlase-blog::admin.settings.comments_close_after_days_label'),
            'comment_link_limit' => __('dixlase-blog::admin.settings.comment_link_limit_label'),
            'comment_rate_max_attempts' => __('dixlase-blog::admin.settings.comment_rate_max_attempts_label'),
            'comment_rate_decay_minutes' => __('dixlase-blog::admin.settings.comment_rate_decay_minutes_label'),
            'comment_notify_email' => __('dixlase-blog::admin.settings.comment_notify_email_label'),
            'comment_ip_retention_days' => __('dixlase-blog::admin.settings.comment_ip_retention_days_label'),
            'privacy_policy_url' => __('dixlase-blog::admin.settings.privacy_policy_url_label'),
        ];
    }
}
