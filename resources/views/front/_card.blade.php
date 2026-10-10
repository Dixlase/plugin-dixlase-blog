{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Single-post card reused by the index and archive listings. Overridable
like index.blade.php.
--}}

@php
    // Translated fields fall back to the source text, so each one carries
    // the lang of the language it is actually shown in.
    $title = $post->localized('title');
    $excerpt = $post->localized('excerpt');
    $url = route('dixlase-blog::front.show', ['slug' => $post->slug]);
@endphp

<article class="dixlase-blog-card">
    @if ($post->featured_media_id && $post->featuredMedia)
        <a href="{{ $url }}" class="dixlase-blog-card__media" tabindex="-1" aria-hidden="true">
            <img src="{{ asset('storage/media/'.$post->featuredMedia->path) }}"
                 alt="{{ $post->featuredMedia->alt_text ?? $title }}"
                 loading="lazy">
        </a>
    @endif

    <div class="dixlase-blog-card__body">
        <div class="dixlase-blog-meta">
            @if ($post->published_at)
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
            @endif
            @if ($post->author)
                <span class="dixlase-blog-meta__author">{{ $post->author->display_name ?? $post->author->account_name }}</span>
            @endif
        </div>

        <h2 class="dixlase-blog-card__title" lang="{{ $post->localizedLang('title') }}">
            <a href="{{ $url }}">{{ $title }}</a>
        </h2>

        @if ($excerpt)
            <p class="dixlase-blog-card__excerpt" lang="{{ $post->localizedLang('excerpt') }}">{{ $excerpt }}</p>
        @endif

        @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
            <div class="dixlase-blog-card__taxonomies">
                @foreach ($post->categories as $category)
                    <a class="dixlase-blog-chip"
                       href="{{ route('dixlase-blog::front.category', ['slug' => $category->slug]) }}"
                       lang="{{ $category->localizedLang('label') }}">{{ $category->localized('label') }}</a>
                @endforeach
                @foreach ($post->tags as $tag)
                    <a class="dixlase-blog-chip dixlase-blog-chip--tag"
                       href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}"
                       lang="{{ $tag->localizedLang('name') }}">#{{ $tag->localized('name') }}</a>
                @endforeach
            </div>
        @endif
    </div>
</article>
