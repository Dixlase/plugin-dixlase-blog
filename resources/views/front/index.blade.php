{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Public blog index. To override it, place a file with the same relative
path under custom/plugins/DixlaseBlog/resources/views/ (one site) or
themes/<theme>/plugins/DixlaseBlog/resources/views/ (one theme).
--}}

@extends('themes::layouts.app')

@section('title', __('dixlase-blog::front.index.title'))
@section('description', __('dixlase-blog::front.index.description'))

@include('dixlase-blog::front._assets')

{{-- In full mode, each HTML post's own CSS (never its JavaScript). --}}
@foreach ($fullPosts ?? [] as $item)
    @if ($item['customCssUrl'])
        @push('styles')
            <link rel="stylesheet" href="{{ $item['customCssUrl'] }}">
        @endpush
    @endif
@endforeach

@section('content')
@component('dixlase-blog::front._layout', ['breadcrumbs' => $breadcrumbs ?? []])
    <section class="dixlase-blog-index">
        <header class="dixlase-blog-index__header">
            <h1 class="dixlase-blog-index__heading">{{ __('dixlase-blog::front.index.heading') }}</h1>
            <p class="dixlase-blog-index__feed">
                <a href="{{ route('dixlase-blog::front.feed') }}" type="application/rss+xml">{{ __('dixlase-blog::front.index.feed_link') }}</a>
            </p>
        </header>

        @if ($posts->isEmpty())
            <p class="dixlase-blog-index__empty">{{ __('dixlase-blog::front.index.no_posts') }}</p>
        @else
            @if (($fullPosts ?? null) !== null)
                {{-- The latest posts in full (index_layout = full). --}}
                <div class="dixlase-blog-full-list">
                    @foreach ($fullPosts as $item)
                        <article class="dixlase-blog-post dixlase-blog-full-list__post">
                            @include('dixlase-blog::front._post-article', [
                                'post' => $item['post'],
                                'contentHtml' => $item['contentHtml'],
                                'headingTag' => 'h2',
                                'titleUrl' => route('dixlase-blog::front.show', ['slug' => $item['post']->slug]),
                            ])
                        </article>
                    @endforeach
                </div>
            @else
                <div class="dixlase-blog-list">
                    @foreach ($posts as $post)
                        @include('dixlase-blog::front._card', ['post' => $post, 'showPinned' => true])
                    @endforeach
                </div>
            @endif

            @include('dixlase-blog::front._pagination')
        @endif
    </section>
@endcomponent
@endsection
