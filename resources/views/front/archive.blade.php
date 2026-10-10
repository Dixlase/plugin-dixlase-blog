{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Shared archive view for category and tag listings; the controller passes
$archiveType ('category' or 'tag'), and $archiveLabel with its language
$archiveLang for the heading.
Themes can override by copying this to
themes/<theme>/views/dixlase-blog/front/archive.blade.php.
--}}

@extends('themes::layouts.app')

@php
    $title = match ($archiveType) {
        'category' => __('dixlase-blog::front.archive.category_title', ['name' => $archiveLabel]),
        'tag' => __('dixlase-blog::front.archive.tag_title', ['name' => $archiveLabel]),
        default => $archiveLabel,
    };
@endphp

@section('title', $title)

@push('head')
    <link rel="alternate" type="application/rss+xml"
          title="{{ __('dixlase-blog::front.feed_title', ['site' => config('app.name')]) }}"
          href="{{ route('dixlase-blog::front.feed') }}">
@endpush

@section('content')
<section class="dixlase-blog-archive">
    <header class="dixlase-blog-archive__header">
        <p class="dixlase-blog-archive__kicker">
            @if ($archiveType === 'category')
                {{ __('dixlase-blog::front.archive.category_kicker') }}
            @else
                {{ __('dixlase-blog::front.archive.tag_kicker') }}
            @endif
        </p>
        <h1 class="dixlase-blog-archive__heading" lang="{{ $archiveLang }}">{{ $archiveLabel }}</h1>
    </header>

    @if ($posts->isEmpty())
        <p class="dixlase-blog-archive__empty">{{ __('dixlase-blog::front.archive.no_posts') }}</p>
    @else
        <div class="dixlase-blog-archive__list">
            @foreach ($posts as $post)
                @include('dixlase-blog::front._card', ['post' => $post])
            @endforeach
        </div>

        <nav class="dixlase-blog-archive__pagination">
            {{ $posts->links() }}
        </nav>
    @endif

    <p class="dixlase-blog-archive__back">
        <a href="{{ route('dixlase-blog::front.index') }}">{{ __('dixlase-blog::front.archive.back_to_index') }}</a>
    </p>
</section>
@endsection
