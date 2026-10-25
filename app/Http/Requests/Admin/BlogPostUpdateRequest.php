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
use App\Rules\UniqueContentSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Plugins\DixlaseBlog\App\Models\BlogPost;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\Concerns\LimitsCustomAssets;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\Concerns\NullsBlankInput;
use Plugins\DixlaseBlog\App\Http\Requests\Admin\Concerns\ReadsDatetimeInDisplayTimezone;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;

class BlogPostUpdateRequest extends FormRequest
{
    use LimitsCustomAssets;
    use NullsBlankInput;
    use ReadsDatetimeInDisplayTimezone;

    /**
     * An HTML (or legacy Blade) body is script-capable: only members who may
     * author scripts may change such a post. See BlogScriptAuthoringPolicy.
     */
    public function authorize(): bool
    {
        $post = $this->postBeingUpdated();

        return $post === null
            || BlogScriptAuthoringPolicy::canEditEditorType(Auth::guard('member')->user(), $post->editor_type);
    }

    /**
     * The post being updated. The {post} route parameter is an id: the
     * admin routes resolve models by name, not by implicit binding.
     */
    public function postBeingUpdated(): ?BlogPost
    {
        $post = $this->route('post');
        if ($post instanceof BlogPost) {
            return $post;
        }

        return is_numeric($post) ? BlogPost::query()->find((int) $post) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_map(fn ($case) => $case->slug(), ContentStatus::cases());

        $post = $this->postBeingUpdated();
        $postId = $post?->id;

        $slugRule = UniqueContentSlug::for('plg_dixlase_blog_posts');
        if ($postId !== null) {
            $slugRule = $slugRule->ignore($postId);
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:'.BlogPost::SLUG_PATTERN, $slugRule],
            'content' => ['nullable', 'string', 'max:500000'],
            // Kept only for HTML posts; see prepareForValidation().
            'custom_css' => ['nullable', 'string', 'max:500000'],
            'custom_js' => ['nullable', 'string', 'max:500000'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            // The editor type is fixed once a post is saved.
            'editor_type' => ['required', 'string', Rule::in($post !== null
                ? [$post->editor_type->slug()]
                : array_map(fn ($case) => $case->slug(), ContentEditorType::cases()))],
            'status' => ['required', 'string', Rule::in($statuses)],
            'is_pinned' => ['sometimes', 'boolean'],
            'comment_status' => ['sometimes', 'string', Rule::in(BlogPost::COMMENT_STATUSES)],
            // A scheduled post appears at published_at, so it needs one.
            'published_at' => ['nullable', 'required_if:status,scheduled', 'date'],
            'closed_at' => ['nullable', 'date', 'after:published_at'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:plg_dixlase_blog_categories,id'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:100'],
            // Stored by DixlaseSEO; only submitted when its integration is on.
            'seo_meta' => ['sometimes', 'array'],
            'seo_meta.description' => ['nullable', 'string', 'max:300'],
            'seo_meta.ogp_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * The media picker submits 0 (or an empty string) for "no image".
     *
     * A post's own CSS and JavaScript: see LimitsCustomAssets.
     */
    protected function prepareForValidation(): void
    {
        $this->nullBlankInput(['published_at', 'closed_at'], ['featured_media_id']);

        // The two datetime-local fields mean the display timezone, not UTC.
        $this->readDatetimeInDisplayTimezone(['published_at', 'closed_at']);

        $post = $this->postBeingUpdated();
        $this->limitCustomAssets($post?->editor_type, $post);

        $seoMeta = $this->input('seo_meta');
        if (is_array($seoMeta) && in_array($seoMeta['ogp_media_id'] ?? null, ['', '0', 0], true)) {
            $seoMeta['ogp_media_id'] = null;
            $this->merge(['seo_meta' => $seoMeta]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'editor_type.in' => __('dixlase-blog::admin.post.editor_type_not_allowed'),
            'published_at.required_if' => __('dixlase-blog::admin.post.scheduled_needs_published_at'),
        ];
    }
}
