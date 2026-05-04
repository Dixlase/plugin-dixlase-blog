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

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $label
 * @property string $slug
 * @property int|null $parent_id
 * @property int $depth
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BlogCategory extends Model
{
    use HasFactory;

    protected $table = 'plg_dixlase_blog_categories';

    /** @var list<string> */
    protected $fillable = [
        'label',
        'slug',
        'parent_id',
        'depth',
        'display_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<self, self>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }

    /**
     * @return BelongsToMany<BlogPost>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'plg_dixlase_blog_post_category',
            'category_id',
            'post_id',
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('display_order');
    }

    /**
     * Compute depth from parent and reject parent assignments that would
     * create a cycle. Runs on every save so depth stays consistent when a
     * category is reparented.
     */
    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $category->depth = self::computeDepthFor($category);
        });
    }

    /**
     * @throws DomainException When the assigned parent would create a cycle.
     */
    protected static function computeDepthFor(self $category): int
    {
        if ($category->parent_id === null) {
            return 0;
        }

        if ($category->exists && (int) $category->parent_id === (int) $category->id) {
            throw new DomainException('Category cannot be its own parent.');
        }

        $cursor = self::find($category->parent_id);
        $depth = 0;
        $visited = [];

        while ($cursor !== null) {
            if ($category->exists && (int) $cursor->id === (int) $category->id) {
                throw new DomainException('Reparenting would create a cycle.');
            }
            if (in_array($cursor->id, $visited, true)) {
                throw new DomainException('Existing category tree contains a cycle.');
            }
            $visited[] = $cursor->id;
            $depth++;
            $cursor = $cursor->parent;
        }

        return $depth;
    }
}
