{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Public blog post page. Overridable like index.blade.php.

The controller passes the body already rendered as $contentHtml (see
BlogContentRenderer).
--}}

@extends('themes::layouts.app')

@php
    // Translated fields fall back to the source text, so each one carries
    // the lang of the language it is actually shown in.
    $title = $post->localized('title');
    $excerpt = $post->localized('excerpt');

    // With DixlaseSEO enabled, it writes the description, canonical and OGP
    // tags into the head; it reads the description through
    // BlogPost::getTranslation('seo_description'). Since DixlaseSEO 0.2.0 it
    // also takes the title, a fallback OGP image (the featured image, used
    // when the post has no OGP image of its own) and the og:type.
    $seoHandlesHead = function_exists('dls_seo_set_page_meta');
    if ($seoHandlesHead) {
        // Positional, not named: DixlaseSEO 0.1.x declares only the first two
        // parameters, and PHP ignores extra positional arguments while an
        // unknown named argument is a fatal Error. Keep this call positional
        // so the page still renders on a site running 0.1.x.
        dls_seo_set_page_meta($post, 'dixlase-blog', $title, $post->featured_media_id, 'article');
    }
@endphp

@section('title', $title)
@section('description', $excerpt ?? '')

@include('dixlase-blog::front._assets')

{{-- Without DixlaseSEO, the post's own tags; they appear once the theme
     renders a head stack. --}}
@unless ($seoHandlesHead)
@push('head')
    @if ($excerpt)
        <meta property="og:description" content="{{ $excerpt }}">
    @endif
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('dixlase-blog::front.show', ['slug' => $post->slug]) }}">
    @if ($post->featuredMedia)
        <meta property="og:image" content="{{ asset('storage/media/'.$post->featuredMedia->path) }}">
    @endif
@endpush
@endunless

@section('content')
@component('dixlase-blog::front._layout', ['breadcrumbs' => $breadcrumbs ?? []])
    <article class="dixlase-blog-post">
        <header class="dixlase-blog-post__header">
            <div class="dixlase-blog-meta">
                @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
                @endif
                @if ($post->author)
                    <span class="dixlase-blog-meta__author">{{ $post->author->display_name ?? $post->author->account_name }}</span>
                @endif
            </div>
            <h1 class="dixlase-blog-post__title" lang="{{ $post->localizedLang('title') }}">{{ $title }}</h1>
        </header>

        @if ($post->featuredMedia)
            <figure class="dixlase-blog-post__featured">
                <img src="{{ asset('storage/media/'.$post->featuredMedia->path) }}"
                     alt="{{ $post->featuredMedia->alt_text ?? $title }}">
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

        @include('dixlase-blog::front._share')
        @include('dixlase-blog::front._post-nav')
        @include('dixlase-blog::front._related')
        @include('dixlase-blog::front._comments')

        <p class="dixlase-blog-back">
            <a href="{{ route('dixlase-blog::front.index') }}">{{ __('dixlase-blog::front.post.back_to_index') }}</a>
        </p>
    </article>
@endcomponent
@endsection
