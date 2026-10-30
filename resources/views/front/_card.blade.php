{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Single-post card reused by the index and archive listings. Overridable
like index.blade.php. The index passes showPinned, as only it lists
pinned posts first. $headingTag is h2 (default) or h3 (the [blog]
shortcode, inside a section with its own heading).
--}}

@php
    // Translated fields fall back to the source text, so each one carries
    // the lang of the language it is actually shown in.
    $title = $post->localized('title');
    $excerpt = $post->localized('excerpt');
    $url = route('dixlase-blog::front.show', ['slug' => $post->slug]);
    $headingTag = ($headingTag ?? 'h2') === 'h3' ? 'h3' : 'h2';
    // What to show beside the title (the listing settings); everything
    // when the card is rendered without them. Cards never show the full
    // text, only the excerpt.
    $fields = $postFields ?? \Plugins\DixlaseBlog\App\Support\BlogSettings::POST_FIELDS;
    $shows = fn (string $field): bool => in_array($field, $fields, true);
    $featuredMedia = $shows('featured_image') ? $post->localizedFeaturedMedia() : null;
    $categories = $shows('categories') ? $post->categories : collect();
    $tags = $shows('tags') ? $post->tags : collect();
    $hasMeta = ($shows('date') && $post->published_at)
        || (($showPinned ?? false) && $post->is_pinned)
        || ($shows('author') && $post->author)
        || ($shows('comments') && ($post->published_comments_count ?? 0) > 0);
@endphp

<article class="dixlase-blog-card">
    @if ($featuredMedia)
        <a href="{{ $url }}" class="dixlase-blog-card__media" tabindex="-1" aria-hidden="true">
            <img src="{{ asset('storage/media/'.$featuredMedia->path) }}"
                 alt="{{ $featuredMedia->alt_text ?? $title }}"
                 loading="lazy">
        </a>
    @endif

    <div class="dixlase-blog-card__body">
        @if ($hasMeta)
            <div class="dixlase-blog-meta">
                @if ($shows('date') && $post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
                @endif
                @if (($showPinned ?? false) && $post->is_pinned)
                    <span class="dixlase-blog-card__pinned">{{ __('dixlase-blog::front.post.pinned') }}</span>
                @endif
                @if ($shows('author') && $post->author)
                    <span class="dixlase-blog-meta__author">{{ $post->author->display_name ?? $post->author->account_name }}</span>
                @endif
                @if ($shows('comments') && ($post->published_comments_count ?? 0) > 0)
                    <span class="dixlase-blog-meta__comments">{{ trans_choice('dixlase-blog::front.comments.count', $post->published_comments_count, ['count' => $post->published_comments_count]) }}</span>
                @endif
            </div>
        @endif

        <{{ $headingTag }} class="dixlase-blog-card__title" lang="{{ $post->localizedLang('title') }}">
            <a href="{{ $url }}">{{ $title }}</a>
        </{{ $headingTag }}>

        @if (($cardExcerpt ?? true) && $excerpt)
            <p class="dixlase-blog-card__excerpt" lang="{{ $post->localizedLang('excerpt') }}">{{ $excerpt }}</p>
        @endif

        @if ($categories->isNotEmpty() || $tags->isNotEmpty())
            <div class="dixlase-blog-card__taxonomies">
                @foreach ($categories as $category)
                    <a class="dixlase-blog-chip"
                       href="{{ route('dixlase-blog::front.category', ['slug' => $category->slug]) }}"
                       lang="{{ $category->localizedLang('label') }}">{{ $category->localized('label') }}</a>
                @endforeach
                @foreach ($tags as $tag)
                    <a class="dixlase-blog-chip dixlase-blog-chip--tag"
                       href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}"
                       lang="{{ $tag->localizedLang('name') }}">#{{ $tag->localized('name') }}</a>
                @endforeach
            </div>
        @endif
    </div>
</article>
