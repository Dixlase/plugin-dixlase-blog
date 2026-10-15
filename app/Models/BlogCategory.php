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
use Plugins\DixlaseBlog\App\Models\Concerns\HasLocalizedFields;

/**
 * @property int $id
 * @property string $label
 * @property string $slug
 * @property string|null $lang Source language of the name; other languages are DixlaseMultilingual overlays
 * @property int|null $parent_id
 * @property int $depth
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BlogCategory extends Model
{
    use HasFactory;
    use HasLocalizedFields;

    protected $table = 'plg_dixlase_blog_categories';

    /**
     * Deepest depth a category may sit at: 0 is top level, so 2 allows
     * three levels (parent, child, grandchild). Deeper trees crowd the
     * breadcrumbs, the sidebar list and the admin tree on a phone.
     */
    public const MAX_DEPTH = 2;

    /** @var list<string> */
    protected $fillable = [
        'label',
        'slug',
        'lang',
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
     * category is reparented. The saved() hook then cascades the depth
     * change to descendants — previously deferred (Phase 2 commit
     * message) and required by Phase 6's drag-and-drop reordering and
     * the existing edit-form parent change.
     */
    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $category->depth = self::computeDepthFor($category);

            // Checked only when the category is placed, so a tree made
            // deeper before the limit still saves on a rename.
            if (! $category->exists || $category->isDirty('parent_id')) {
                $height = $category->exists ? $category->subtreeHeight() : 0;
                if ($category->depth + $height > self::MAX_DEPTH) {
                    throw new DomainException(self::tooDeepMessage());
                }
            }
        });

        static::saved(function (self $category): void {
            if ($category->wasChanged('parent_id') || $category->wasChanged('depth')) {
                $category->fixDescendantDepths();
            }
        });
    }

    /**
     * Recursively rewrite this node's descendants' depths so they are
     * consistent with this node's depth. Uses raw query-builder updates
     * so children's saving/saved hooks do not fire (which would loop and
     * also re-run cycle detection unnecessarily). Only writes when the
     * value actually differs, so a no-op reparent costs at most one
     * SELECT per descendant.
     */
    public function fixDescendantDepths(): void
    {
        $children = self::query()->where('parent_id', $this->id)->get();
        foreach ($children as $child) {
            $newDepth = (int) $this->depth + 1;
            if ((int) $child->depth !== $newDepth) {
                self::query()->where('id', $child->id)->update(['depth' => $newDepth]);
                $child->depth = $newDepth;
            }
            $child->fixDescendantDepths();
        }
    }

    /**
     * Levels of descendants below this category: 0 without children, 1
     * with children only, 2 with grandchildren.
     */
    public function subtreeHeight(): int
    {
        $height = 0;
        $ids = [$this->id];
        $seen = [$this->id];

        while (true) {
            $ids = self::query()->whereIn('parent_id', $ids)->whereNotIn('id', $seen)->pluck('id')->all();
            if ($ids === []) {
                return $height;
            }
            $seen = array_merge($seen, $ids);
            $height++;
        }
    }

    /**
     * Whether a child may be added under this category.
     */
    public function canHaveChildren(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }

    public static function tooDeepMessage(): string
    {
        return __('dixlase-blog::admin.category.too_deep', ['levels' => self::MAX_DEPTH + 1]);
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
