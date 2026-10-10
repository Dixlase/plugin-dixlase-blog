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

use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Helpers\LocaleHelper;
use App\Rules\UniqueContentSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostStoreRequest extends FormRequest
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
        $editorTypes = array_map(fn ($case) => $case->slug(), ContentEditorType::cases());
        $statuses = array_map(fn ($case) => $case->slug(), ContentStatus::cases());

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:200', 'regex:/^[a-z0-9][a-z0-9\-]*$/',
                UniqueContentSlug::for('plg_dixlase_blog_posts'),
            ],
            'content' => ['nullable', 'string', 'max:500000'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'editor_type' => ['required', 'string', Rule::in($editorTypes)],
            'status' => ['required', 'string', Rule::in($statuses)],
            'lang' => ['nullable', 'string', Rule::in(LocaleHelper::supportedLocales())],
            'published_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date', 'after:published_at'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:plg_dixlase_blog_categories,id'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:100'],
        ];
    }
}
