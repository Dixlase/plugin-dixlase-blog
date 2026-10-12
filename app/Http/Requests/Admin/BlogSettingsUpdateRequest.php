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
use Illuminate\Validation\Rule;
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
            'posts_per_page' => [
                'sometimes',
                'required',
                'integer',
                'min:'.BlogSettings::POSTS_PER_PAGE_MIN,
                'max:'.BlogSettings::POSTS_PER_PAGE_MAX,
            ],
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
            'sidebar_position' => __('dixlase-blog::admin.settings.sidebar_position_label'),
            'sidebar_recent_count' => __('dixlase-blog::admin.settings.sidebar_recent_count_label'),
        ];
    }
}
