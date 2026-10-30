{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

One post as an article: date and author, title, featured image, body,
categories and tags. The post page shows it once (h1); the blog's top
page in "full" mode shows each of its latest posts with it (h2, the
title linking to the post).

Takes $post and $contentHtml (BlogContentRenderer). Optional:
$headingTag ('h1' by default, or 'h2' / 'h3') and $titleUrl (a link on the title).
In a listing, $fields (BlogSettings::POST_FIELDS) picks what shows beside
the title, and a null $contentHtml shows the excerpt with a link to the
post ($titleUrl) instead of the body. The post page passes neither.
--}}

@php
    $headingTag = in_array($headingTag ?? 'h1', ['h2', 'h3'], true) ? $headingTag : 'h1';
    $titleUrl ??= null;
    $title = $post->localized('title');
    // The post page shows everything; a listing passes its fields.
    $fields ??= null;
    $contentHtml ??= null;
    $shows = fn (string $field): bool => $fields === null || in_array($field, $fields, true);
    $featuredMedia = $shows('featured_image') ? $post->localizedFeaturedMedia() : null;
    $categories = $shows('categories') ? $post->categories : collect();
    $tags = $shows('tags') ? $post->tags : collect();
    // The comment count only in a listing; the post page lists the comments.
    $commentCount = $fields !== null && $shows('comments') ? (int) ($post->published_comments_count ?? 0) : 0;
    $hasMeta = ($shows('date') && $post->published_at) || ($shows('author') && $post->author) || $commentCount > 0;
@endphp

<header class="dixlase-blog-post__header">
    @if ($hasMeta)
        <div class="dixlase-blog-meta">
            @if ($shows('date') && $post->published_at)
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
            @endif
            @if ($shows('author') && $post->author)
                <span class="dixlase-blog-meta__author">{{ $post->author->display_name ?? $post->author->account_name }}</span>
            @endif
            @if ($commentCount > 0)
                <span class="dixlase-blog-meta__comments">{{ trans_choice('dixlase-blog::front.comments.count', $commentCount, ['count' => $commentCount]) }}</span>
            @endif
        </div>
    @endif
    <{{ $headingTag }} class="dixlase-blog-post__title" lang="{{ $post->localizedLang('title') }}">@if ($titleUrl)<a href="{{ $titleUrl }}">{{ $title }}</a>@else{{ $title }}@endif</{{ $headingTag }}>
</header>

@if ($featuredMedia)
    <figure class="dixlase-blog-post__featured">
        <img src="{{ asset('storage/media/'.$featuredMedia->path) }}"
             alt="{{ $featuredMedia->alt_text ?? $title }}">
    </figure>
@endif

@if ($contentHtml !== null)
    <div class="dixlase-blog-post__content dixlase-blog-prose" lang="{{ $post->localizedLang('content') }}">
        {{-- Rendered by BlogContentRenderer: Markdown with raw HTML escaped,
             HTML as authored by an admin, anything else as escaped text. --}}
        {!! $contentHtml !!}
    </div>
@else
    {{-- The excerpt instead of the body (the latest posts, excerpt mode). --}}
    @php $excerpt = $post->localized('excerpt'); @endphp
    @if ($excerpt)
        <p class="dixlase-blog-post__excerpt" lang="{{ $post->localizedLang('excerpt') }}">{{ $excerpt }}</p>
    @endif
    @if ($titleUrl)
        <p class="dixlase-blog-post__more">
            <a href="{{ $titleUrl }}" aria-label="{{ __('dixlase-blog::front.post.read_more_label', ['title' => $title]) }}">{{ __('dixlase-blog::front.post.read_more') }}</a>
        </p>
    @endif
@endif

@if ($categories->isNotEmpty() || $tags->isNotEmpty())
    <footer class="dixlase-blog-post__taxonomies">
        @if ($categories->isNotEmpty())
            <div class="dixlase-blog-post__taxonomy">
                <span class="dixlase-blog-post__taxonomy-label">{{ __('dixlase-blog::front.taxonomy.categories') }}</span>
                @foreach ($categories as $category)
                    <a class="dixlase-blog-chip"
                       href="{{ route('dixlase-blog::front.category', ['slug' => $category->slug]) }}"
                       lang="{{ $category->localizedLang('label') }}">{{ $category->localized('label') }}</a>
                @endforeach
            </div>
        @endif
        @if ($tags->isNotEmpty())
            <div class="dixlase-blog-post__taxonomy">
                <span class="dixlase-blog-post__taxonomy-label">{{ __('dixlase-blog::front.taxonomy.tags') }}</span>
                @foreach ($tags as $tag)
                    <a class="dixlase-blog-chip dixlase-blog-chip--tag"
                       href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}"
                       lang="{{ $tag->localizedLang('name') }}">#{{ $tag->localized('name') }}</a>
                @endforeach
            </div>
        @endif
    </footer>
@endif
