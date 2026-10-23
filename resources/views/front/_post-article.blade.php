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
$headingTag ('h1' by default) and $titleUrl (a link on the title).
--}}

@php
    $headingTag = ($headingTag ?? 'h1') === 'h2' ? 'h2' : 'h1';
    $titleUrl ??= null;
    $title = $post->localized('title');
    $featuredMedia = $post->localizedFeaturedMedia();
@endphp

<header class="dixlase-blog-post__header">
    <div class="dixlase-blog-meta">
        @if ($post->published_at)
            <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
        @endif
        @if ($post->author)
            <span class="dixlase-blog-meta__author">{{ $post->author->display_name ?? $post->author->account_name }}</span>
        @endif
    </div>
    <{{ $headingTag }} class="dixlase-blog-post__title" lang="{{ $post->localizedLang('title') }}">@if ($titleUrl)<a href="{{ $titleUrl }}">{{ $title }}</a>@else{{ $title }}@endif</{{ $headingTag }}>
</header>

@if ($featuredMedia)
    <figure class="dixlase-blog-post__featured">
        <img src="{{ asset('storage/media/'.$featuredMedia->path) }}"
             alt="{{ $featuredMedia->alt_text ?? $title }}">
    </figure>
@endif

<div class="dixlase-blog-post__content dixlase-blog-prose" lang="{{ $post->localizedLang('content') }}">
    {{-- Rendered by BlogContentRenderer: Markdown with raw HTML escaped,
         HTML as authored by an admin, anything else as escaped text. --}}
    {!! $contentHtml !!}
</div>

@if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
    <footer class="dixlase-blog-post__taxonomies">
        @if ($post->categories->isNotEmpty())
            <div class="dixlase-blog-post__taxonomy">
                <span class="dixlase-blog-post__taxonomy-label">{{ __('dixlase-blog::front.taxonomy.categories') }}</span>
                @foreach ($post->categories as $category)
                    <a class="dixlase-blog-chip"
                       href="{{ route('dixlase-blog::front.category', ['slug' => $category->slug]) }}"
                       lang="{{ $category->localizedLang('label') }}">{{ $category->localized('label') }}</a>
                @endforeach
            </div>
        @endif
        @if ($post->tags->isNotEmpty())
            <div class="dixlase-blog-post__taxonomy">
                <span class="dixlase-blog-post__taxonomy-label">{{ __('dixlase-blog::front.taxonomy.tags') }}</span>
                @foreach ($post->tags as $tag)
                    <a class="dixlase-blog-chip dixlase-blog-chip--tag"
                       href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}"
                       lang="{{ $tag->localizedLang('name') }}">#{{ $tag->localized('name') }}</a>
                @endforeach
            </div>
        @endif
    </footer>
@endif
