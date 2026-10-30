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

namespace Plugins\DixlaseBlog\App\Http\Requests\Admin;

use App\Helpers\LocaleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\Concerns\NullsBlankInput;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Models\BlogTag;

/**
 * Adding or renaming a tag on the Tags screen. The name and the slug are
 * each unique across tags; a blank slug is derived from the name.
 */
class BlogTagRequest extends FormRequest
{
    use NullsBlankInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Read by name, as the controllers do: {tag} on update, none on store.
        $tagId = $this->route('tag');
        $unique = fn (string $column) => Rule::unique('plg_dixlase_blog_tags', $column)
            ->ignore($tagId !== null ? (int) $tagId : null);

        return [
            'name' => ['required', 'string', 'max:'.BlogTag::NAME_MAX, $unique('name')],
            'slug' => ['nullable', 'string', 'max:'.BlogTag::NAME_MAX, 'regex:'.BlogPost::SLUG_PATTERN, $unique('slug')],
            // Used only while the site serves several languages.
            'lang' => ['nullable', 'string', Rule::in(LocaleHelper::supportedLocales())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('dixlase-blog::admin.tags.name'),
            'slug' => __('dixlase-blog::admin.tags.slug'),
            'lang' => __('dixlase-blog::admin.tags.lang'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
        $this->nullBlankInput(['slug', 'lang']);
    }
}
