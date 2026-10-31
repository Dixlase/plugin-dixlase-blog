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

use App\Contracts\Content\DeclaresScriptCapableParts;
use App\Contracts\Content\ProvidesFileContentEditLink;
use App\Contracts\Content\PublishesFileContent;
use App\Contracts\Revisionable;
use App\Enums\ContentEditorType;
use App\Enums\ContentPublishMode;
use App\Enums\ContentStatus;
use App\Enums\ContentStorageType;
use App\Models\Media;
use App\Models\Member;
use App\Services\Content\FileContentPublisher;
use App\Traits\HasRevisions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plugins\DixlaseBlog\App\Models\Concerns\HasLocalizedFields;
use Plugins\DixlaseBlog\App\Services\BlogPostFiles;
use Plugins\DixlaseBlog\App\Services\BlogScriptAuthoringPolicy;
use Plugins\DixlaseBlog\App\Services\BlogSeoMeta;
use Plugins\DixlaseBlog\App\Support\BlogExcerpt;
use Plugins\DixlaseBlog\App\Support\BlogSettings;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property string|null $custom_css Served only for HTML and Markdown posts
 * @property string|null $custom_js Served only for HTML posts
 * @property string|null $excerpt
 * @property int|null $featured_media_id
 * @property ContentEditorType $editor_type
 * @property ContentStorageType $storage_type Files hold a working copy; the columns hold what is published
 * @property ContentStatus $status
 * @property bool $is_pinned
 * @property string $comment_status inherit, open or closed (COMMENT_STATUSES)
 * @property string|null $lang Source language; other languages are translation overlays
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class BlogPost extends Model implements DeclaresScriptCapableParts, ProvidesFileContentEditLink, PublishesFileContent, Revisionable
{
    use HasFactory;
    use HasLocalizedFields;
    use HasRevisions;
    use SoftDeletes;

    public const PLUGIN_SLUG = 'dixlase-blog';

    /** Whether a post takes comments: the blog setting, or its own choice. */
    /**
     * Slugs of posts and categories: lowercase letters, digits, hyphens and
     * underscores, starting with a letter or digit.
     */
    public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_\-]*$/';

    public const COMMENT_STATUSES = ['inherit', 'open', 'closed'];

    protected $table = 'plg_dixlase_blog_posts';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'custom_css',
        'custom_js',
        'excerpt',
        'featured_media_id',
        'editor_type',
        'storage_type',
        'status',
        'is_pinned',
        'comment_status',
        'lang',
        'user_id',
        'published_at',
        'closed_at',
    ];

    /**
     * Matches the column default, so a new post carries it before it is
     * read back: the first revision snapshot is taken from this instance,
     * and a null there would differ from every later snapshot.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_pinned' => false,
        'comment_status' => 'inherit',
        'storage_type' => 0,
    ];

    protected static function booted(): void
    {
        // The SEO meta row belongs to the post; a soft delete keeps it so a
        // restored post gets it back.
        static::forceDeleted(function (BlogPost $post): void {
            app(BlogSeoMeta::class)->delete((int) $post->id);
            app(BlogPostFiles::class)->delete($post);
        });

        // A body published from its files, rather than saved from the form,
        // keeps an excerpt that was made from it in step with it. One the
        // author wrote is left alone.
        static::saving(function (BlogPost $post): void {
            if (! $post->exists || ! $post->isDirty('content') || $post->isDirty('excerpt')) {
                return;
            }
            $previous = BlogExcerpt::fromContent($post->getOriginal('content'));
            if ((string) $post->getOriginal('excerpt') === (string) $previous) {
                $post->excerpt = BlogExcerpt::fromContent($post->getAttributes()['content'] ?? null);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'editor_type' => ContentEditorType::class,
            'storage_type' => ContentStorageType::class,
            'status' => ContentStatus::class,
            'is_pinned' => 'boolean',
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

    /** @var array<int, Media|null> Translated featured images, by media id. */
    private array $translatedFeaturedMedia = [];

    /**
     * The featured image to show in a locale: the one set for it in the
     * translation editor, else the post's own. A
     * translated image whose media item is gone falls back as well.
     */
    public function localizedFeaturedMedia(?string $locale = null): ?Media
    {
        $id = $this->translationFor('featured_media_id', $locale);
        if ($id === null || ! ctype_digit($id)) {
            return $this->featuredMedia;
        }

        $id = (int) $id;
        if (! array_key_exists($id, $this->translatedFeaturedMedia)) {
            $this->translatedFeaturedMedia[$id] = Media::query()->find($id);
        }

        return $this->translatedFeaturedMedia[$id] ?? $this->featuredMedia;
    }

    /**
     * The id of that image, for DixlaseSEO's fallback OGP image. The post's
     * own id is handed on even when its media item is not loaded, since
     * DixlaseSEO resolves the id itself.
     */
    public function localizedFeaturedMediaId(?string $locale = null): ?int
    {
        return $this->localizedFeaturedMedia($locale)?->id ?? $this->featured_media_id;
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
     * Every comment, in any state; see BlogComment.
     *
     * @return HasMany<BlogComment>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'post_id');
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
     * Apply all three public-visibility filters together: PUBLISHED or
     * SCHEDULED status, non-null published_at not in the future, and a
     * closed_at that is either unset or still in the future. Used by every
     * public-facing query, so a SCHEDULED post appears once its
     * published_at passes (as in DixlasePages; nothing flips the status)
     * and no post shows before published_at or after closed_at.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        $now = now();

        return $query
            ->whereIn('status', [ContentStatus::PUBLISHED->value, ContentStatus::SCHEDULED->value])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('closed_at')->orWhere('closed_at', '>', $now);
            });
    }

    /**
     * Adds published_comments_count, shown on the post cards.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithPublishedCommentCount(Builder $query): Builder
    {
        return $query->withCount([
            'comments as published_comments_count' => fn (Builder $q) => $q->where('status', BlogComment::STATUS_PUBLISHED),
        ]);
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

    /**
     * A translation that carries content but no excerpt gets one derived
     * from the translated content, the same way a source excerpt is
     * generated on save, rather than falling back to the source excerpt.
     */
    public function translationFor(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $value = $this->resolveOverlay($field, $locale);

        if ($value === null && $field === 'excerpt') {
            $value = BlogExcerpt::fromContent($this->resolveOverlay('content', $locale));
        }

        return $value;
    }

    /**
     * The SEO description stored by DixlaseSEO, in the source language.
     * Exposed as an attribute so the translation editor shows it as
     * the source text of the translatable seo_description field.
     */
    public function getSeoDescriptionAttribute(): ?string
    {
        return $this->exists ? app(BlogSeoMeta::class)->description($this) : null;
    }

    /**
     * Translation lookup in the shape DixlaseSEO's page-meta helper calls
     * (TranslatableTrait's signature). For seo_description it never comes
     * back empty: a translated description, else the stored one in the
     * source language, else the post's excerpt in the requested locale.
     *
     * For seo_ogp_media_id it is the image made for the requested locale:
     * the translated OGP image, else the translated featured image. An
     * image in the reader's language comes before the source language's
     * SEO image, which DixlaseSEO tries next.
     */
    public function getTranslation(string $field, ?string $locale = null, bool $fallback = true): mixed
    {
        $locale ??= app()->getLocale();
        $value = $this->translationFor($field, $locale);
        if ($value !== null) {
            return $value;
        }

        if ($field === 'seo_description') {
            $stored = $locale === $this->sourceLocale() ? $this->seo_description : null;

            return $stored ?? $this->localized('excerpt', $locale);
        }

        if ($field === 'seo_ogp_media_id') {
            return $this->translationFor('featured_media_id', $locale);
        }

        return $fallback ? $this->getAttribute($field) : null;
    }

    /**
     * Whether the public pages show this post now; the same test as
     * scopeCurrentlyVisible().
     */
    public function isCurrentlyVisible(): bool
    {
        return $this->isPublished()
            && $this->published_at !== null
            && ! $this->published_at->isFuture()
            && ($this->closed_at === null || $this->closed_at->isFuture());
    }

    public function isPublished(): bool
    {
        return match ($this->status) {
            ContentStatus::PUBLISHED => true,
            ContentStatus::SCHEDULED => $this->published_at !== null && $this->published_at->isPast(),
            default => false,
        };
    }

    /**
     * Take in, or record, a change made to the post's files outside the
     * admin, before the post is shown. Core's FileContentPublisher decides
     * which: the columns keep the published copy, and a change waits there
     * until someone publishes it, except under the file save mode in a
     * local environment.
     */
    public function checkFiles(): void
    {
        if ($this->usesFileStorage()) {
            app(FileContentPublisher::class)->check($this);
        }
    }

    public function usesFileStorage(): bool
    {
        return $this->storage_type === ContentStorageType::FILE;
    }

    /**
     * What the front should show for a part the post stores as files
     *
     * The columns hold the published copy; the files hold the working one.
     * Core's resolver decides which the visitor sees, and it answers the
     * same on every core: the files while a change is waiting under a mode
     * that shows it, the column otherwise.
     *
     * Reading the column directly was right only by accident. Up to core
     * 0.1.20 a local environment took each change into the column as it was
     * seen, so the column already held the edit. Core holds it from
     * dixlase-core#808 on, and a post read from the column would then stop
     * showing a local edit until someone published it (#197).
     */
    public function resolvedPart(string $part): ?string
    {
        if ($this->usesFileStorage() && array_key_exists($part, $this->fileContentParts())) {
            return app(FileContentPublisher::class)->resolve($this, $part);
        }

        return $this->getAttribute($part);
    }

    /**
     * The mode chosen in the blog settings
     *
     * Core decides where it takes effect: the file save mode applies only in
     * a local environment, and anywhere else every change to the files waits
     * to be published (FileContentPublisher::effectivePublishMode()).
     */
    public function fileContentPublishMode(): ContentPublishMode
    {
        return app(BlogSettings::class)->publishMode();
    }

    /**
     * The body, plus the CSS (HTML and Markdown) and the JavaScript (HTML
     * alone), as BlogScriptAuthoringPolicy keeps them
     *
     * @return array<string, string>
     */
    public function fileContentParts(): array
    {
        $parts = ['content' => 'content'];

        if (BlogScriptAuthoringPolicy::carriesCustomCss($this->editor_type)) {
            $parts['custom_css'] = 'custom_css';
        }
        if (BlogScriptAuthoringPolicy::carriesCustomJs($this->editor_type)) {
            $parts['custom_js'] = 'custom_js';
        }

        return $parts;
    }

    /**
     * The parts of a post that may carry a script
     *
     * The CSS and the JavaScript, and the body under the HTML editor, where
     * `<script>` is part of what the author writes.
     *
     * @return list<string>
     */
    public function scriptCapableFileContentParts(): array
    {
        $parts = FileContentPublisher::DEFAULT_SCRIPT_CAPABLE_PARTS;

        if ($this->editor_type === ContentEditorType::HTML) {
            $parts[] = 'content';
        }

        return $parts;
    }

    public function fileContentPath(string $part): string
    {
        return app(BlogPostFiles::class)->path($this, $part);
    }

    public function fileContentDisk(): string
    {
        return BlogPostFiles::DISK;
    }

    public function fileContentAuditPrefix(): string
    {
        return 'dixlase_blog.post';
    }

    /**
     * @return Builder<static>
     */
    public static function fileStoredQuery(): Builder
    {
        return static::query()->where('storage_type', ContentStorageType::FILE->value);
    }

    public function fileContentLabel(): string
    {
        return (string) $this->title;
    }

    public function fileContentEditUrl(): ?string
    {
        return route('dixlase-blog::admin.blog.edit', ['post' => $this->getKey()]);
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
            'custom_css',
            'custom_js',
            'excerpt',
            'featured_media_id',
            'editor_type',
            'status',
            'is_pinned',
            'comment_status',
            'lang',
            'user_id',
            'published_at',
            'closed_at',
        ];
    }
}
