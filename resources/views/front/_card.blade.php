{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Single-post card partial reused by the index and archive listings.
Themes can override by copying this to
themes/<theme>/views/dixlase-blog/front/_card.blade.php.
--}}

@php
    // Translated fields fall back to the source text, so each one carries
    // the lang of the language it is actually shown in.
    $title = $post->localized('title');
    $excerpt = $post->localized('excerpt');
@endphp

<article class="dixlase-blog-card">
    @if ($post->featured_media_id && $post->featuredMedia)
        <a href="{{ route('dixlase-blog::front.show', ['slug' => $post->slug]) }}" class="dixlase-blog-card__media">
            <img src="{{ asset('storage/media/'.$post->featuredMedia->path) }}"
                 alt="{{ $post->featuredMedia->alt_text ?? $title }}"
                 loading="lazy">
        </a>
    @endif

    <div class="dixlase-blog-card__body">
        <h2 class="dixlase-blog-card__title" lang="{{ $post->localizedLang('title') }}">
            <a href="{{ route('dixlase-blog::front.show', ['slug' => $post->slug]) }}">{{ $title }}</a>
        </h2>

        <div class="dixlase-blog-card__meta">
            @if ($post->published_at)
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
            @endif
            @if ($post->author)
                <span class="dixlase-blog-card__author">{{ $post->author->display_name ?? $post->author->account_name }}</span>
            @endif
        </div>

        @if ($excerpt)
            <p class="dixlase-blog-card__excerpt" lang="{{ $post->localizedLang('excerpt') }}">{{ $excerpt }}</p>
        @endif

        @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
            <div class="dixlase-blog-card__taxonomies">
                @foreach ($post->categories as $category)
                    <a href="{{ route('dixlase-blog::front.category', ['slug' => $category->slug]) }}"
                       class="dixlase-blog-card__category">{{ $category->label }}</a>
                @endforeach
                @foreach ($post->tags as $tag)
                    <a href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}"
                       class="dixlase-blog-card__tag">#{{ $tag->name }}</a>
                @endforeach
            </div>
        @endif
    </div>
</article>
