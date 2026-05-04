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

use App\Models\Member;
use App\Services\RevisionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property array<string, mixed> $snapshot
 * @property string $type
 * @property string|null $note
 * @property bool $is_protected
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class BlogPostRevision extends Model
{
    protected $table = 'plg_dixlase_blog_post_revisions';

    public const TYPE_AUTO = RevisionService::TYPE_AUTO;

    public const TYPE_MANUAL = RevisionService::TYPE_MANUAL;

    public const TYPE_RESTORE_BACKUP = RevisionService::TYPE_RESTORE_BACKUP;

    /** @var list<string> */
    protected $fillable = [
        'post_id',
        'snapshot',
        'type',
        'note',
        'is_protected',
        'created_by',
    ];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'is_protected' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The relation name "post" matches the FK convention (post_id) so the
     * core RevisionService::loadTarget() finds it without configuration.
     *
     * @return BelongsTo<BlogPost, self>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }

    /**
     * @return BelongsTo<Member, self>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            if (empty($revision->created_at)) {
                $revision->created_at = now();
            }
        });
    }
}
