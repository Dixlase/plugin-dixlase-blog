{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Public blog index. Themes can override by copying this to
themes/<theme>/views/dixlase-blog/front/index.blade.php.
--}}

@extends('themes::layouts.app')

@section('title', __('dixlase-blog::front.index.title'))
@section('description', __('dixlase-blog::front.index.description'))

@push('head')
    <link rel="alternate" type="application/rss+xml"
          title="{{ __('dixlase-blog::front.feed_title', ['site' => config('app.name')]) }}"
          href="{{ route('dixlase-blog::front.feed') }}">
@endpush

@section('content')
<section class="dixlase-blog-index">
    <header class="dixlase-blog-index__header">
        <h1>{{ __('dixlase-blog::front.index.heading') }}</h1>
        {{-- Visible as well as in <head>: the feed follows the current
             locale, so each language's index links its own feed. --}}
        <p class="dixlase-blog-index__feed">
            <a href="{{ route('dixlase-blog::front.feed') }}" type="application/rss+xml">{{ __('dixlase-blog::front.index.feed_link') }}</a>
        </p>
    </header>

    @if ($posts->isEmpty())
        <p class="dixlase-blog-index__empty">{{ __('dixlase-blog::front.index.no_posts') }}</p>
    @else
        <div class="dixlase-blog-index__list">
            @foreach ($posts as $post)
                @include('dixlase-blog::front._card', ['post' => $post])
            @endforeach
        </div>

        <nav class="dixlase-blog-index__pagination">
            {{ $posts->links() }}
        </nav>
    @endif
</section>
@endsection
