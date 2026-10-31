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

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Plugins\DixlaseBlog\App\Models\Concerns\HasLocalizedFields;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $lang Source language of the name; other languages are translation overlays
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BlogTag extends Model
{
    use HasFactory;
    use HasLocalizedFields;

    /** The name and slug columns are 100 characters. */
    public const NAME_MAX = 100;

    protected $table = 'plg_dixlase_blog_tags';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'lang',
    ];

    /**
     * A slug no other tag uses: the given one, else one derived from the
     * name (a hash when the name has no Latin letters), numbered on a
     * clash. $exceptId is the tag being renamed.
     */
    public static function freeSlug(?string $candidate, string $name, ?int $exceptId = null): string
    {
        $base = trim((string) $candidate);
        if ($base === '') {
            $base = Str::slug($name);
        }
        if ($base === '') {
            $base = 'tag-'.substr(md5($name), 0, 8);
        }
        $base = Str::limit($base, self::NAME_MAX - 4, '');

        $slug = $base;
        for ($n = 2; static::query()->where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /**
     * @return BelongsToMany<BlogPost>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'plg_dixlase_blog_post_tag',
            'tag_id',
            'post_id',
        );
    }
}
