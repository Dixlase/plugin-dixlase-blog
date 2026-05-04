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

namespace Plugins\DixlaseBlog\App\Models;

use App\Contracts\Revisionable;
use App\Enums\ContentEditorType;
use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\Member;
use App\Traits\HasRevisions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property string|null $excerpt
 * @property int|null $featured_media_id
 * @property ContentEditorType $editor_type
 * @property ContentStatus $status
 * @property string|null $lang
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class BlogPost extends Model implements Revisionable
{
    use HasFactory;
    use HasRevisions;
    use SoftDeletes;

    public const PLUGIN_SLUG = 'dixlase-blog';

    protected $table = 'plg_dixlase_blog_posts';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_media_id',
        'editor_type',
        'status',
        'lang',
        'user_id',
        'published_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'editor_type' => ContentEditorType::class,
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, self>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'user_id');
    }

    /**
     * Featured image as a Media reference. The relation is intentionally
     * resolvable to null when the underlying media row has been deleted
     * (no FK constraint at the DB layer — see migration comment).
     *
     * @return BelongsTo<Media, self>
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * @return BelongsToMany<BlogCategory>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogCategory::class,
            'plg_dixlase_blog_post_category',
            'post_id',
            'category_id',
        );
    }

    /**
     * @return BelongsToMany<BlogTag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogTag::class,
            'plg_dixlase_blog_post_tag',
            'post_id',
            'tag_id',
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::PUBLISHED->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForLang(Builder $query, string $lang): Builder
    {
        return $query->where('lang', $lang);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::PUBLISHED;
    }

    public function revisionModel(): string
    {
        return BlogPostRevision::class;
    }

    public function revisionForeignKey(): string
    {
        return 'post_id';
    }

    /**
     * Column-level snapshot fields. Category and tag pivot data are layered
     * onto the snapshot by BlogPostService at record time, since pivots are
     * not direct columns and the core RevisionService snapshots column data
     * only.
     *
     * @return list<string>
     */
    public function revisionableFields(): array
    {
        return [
            'title',
            'slug',
            'content',
            'excerpt',
            'featured_media_id',
            'editor_type',
            'status',
            'lang',
            'user_id',
            'published_at',
            'closed_at',
        ];
    }
}
