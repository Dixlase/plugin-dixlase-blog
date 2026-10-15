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
use Plugins\DixlaseBlog\App\Http\Requests\Admin\Concerns\NullsBlankInput;
use Plugins\DixlaseBlog\App\Models\BlogCategory;
use Plugins\DixlaseBlog\App\Models\BlogPost;

class BlogCategoryUpdateRequest extends FormRequest
{
    use NullsBlankInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The category being updated. The {category} route parameter is an id:
     * the admin routes resolve models by name, not by implicit binding.
     */
    public function categoryBeingUpdated(): ?BlogCategory
    {
        $category = $this->route('category');
        if ($category instanceof BlogCategory) {
            return $category;
        }

        return is_numeric($category) ? BlogCategory::query()->find((int) $category) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryId = $this->categoryBeingUpdated()?->id;

        $slugRule = Rule::unique('plg_dixlase_blog_categories', 'slug');
        if ($categoryId !== null) {
            $slugRule = $slugRule->ignore($categoryId);
        }

        return [
            'label' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:'.BlogPost::SLUG_PATTERN, $slugRule],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:plg_dixlase_blog_categories,id',
                // Disallow self-parent at the validation layer; the model's
                // saving hook also catches this and any deeper cycles.
                $categoryId !== null
                    ? Rule::notIn([$categoryId])
                    : 'string',
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankInput(['slug', 'display_order'], ['parent_id']);
    }
}
