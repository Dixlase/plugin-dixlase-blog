{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Public blog post detail page. Themes can override by copying this to
themes/<theme>/views/dixlase-blog/front/show.blade.php.

Editor type rendering:
  - markdown: parsed via Str::markdown() (CommonMark)
  - html:     output as-is (admin authors are trusted)
  - other:    escaped as plain text (gui = future block editor, blade =
              not supported per Phase 7 scope decision)
--}}

@extends('themes::layouts.app')

@php
    // Translated fields fall back to the source text, so each one carries
    // the lang of the language it is actually shown in.
    $title = $post->localized('title');
    $excerpt = $post->localized('excerpt');
    $content = $post->localized('content');
@endphp

@section('title', $title)
@section('description', $excerpt ?? '')

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

@section('content')
<article class="dixlase-blog-post">
    @if ($post->featuredMedia)
        <figure class="dixlase-blog-post__featured">
            <img src="{{ asset('storage/media/'.$post->featuredMedia->path) }}"
                 alt="{{ $post->featuredMedia->alt_text ?? $title }}">
        </figure>
    @endif

    <header class="dixlase-blog-post__header">
        <h1 class="dixlase-blog-post__title" lang="{{ $post->localizedLang('title') }}">{{ $title }}</h1>
        <div class="dixlase-blog-post__meta">
            @if ($post->published_at)
                <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
            @endif
            @if ($post->author)
                <span class="dixlase-blog-post__author">{{ $post->author->display_name ?? $post->author->account_name }}</span>
            @endif
        </div>
    </header>

    <div class="dixlase-blog-post__content prose" lang="{{ $post->localizedLang('content') }}">
        @switch($post->editor_type?->slug())
            @case('markdown')
                {!! \Illuminate\Support\Str::markdown($content ?? '') !!}
                @break
            @case('html')
                {!! $content !!}
                @break
            @default
                {{-- Fallback for unsupported editor types (gui / blade etc.):
                     output the raw stored content as escaped plain text so
                     no script tag from a future GUI-editor JSON payload can
                     be interpreted. --}}
                <pre>{{ $content }}</pre>
        @endswitch
    </div>

    @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
        <footer class="dixlase-blog-post__taxonomies">
            @if ($post->categories->isNotEmpty())
                <div class="dixlase-blog-post__categories">
                    <span class="dixlase-blog-post__taxonomy-label">{{ __('dixlase-blog::front.taxonomy.categories') }}:</span>
                    @foreach ($post->categories as $category)
                        <a href="{{ route('dixlase-blog::front.category', ['slug' => $category->slug]) }}">{{ $category->label }}</a>
                    @endforeach
                </div>
            @endif
            @if ($post->tags->isNotEmpty())
                <div class="dixlase-blog-post__tags">
                    <span class="dixlase-blog-post__taxonomy-label">{{ __('dixlase-blog::front.taxonomy.tags') }}:</span>
                    @foreach ($post->tags as $tag)
                        <a href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}">#{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif
        </footer>
    @endif
</article>
@endsection
