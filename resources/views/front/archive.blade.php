{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Shared archive view for category and tag listings; the controller passes
$archiveType ('category' or 'tag'), and $archiveLabel with its language
$archiveLang for the heading. Overridable like index.blade.php.
--}}

@extends('themes::layouts.app')

@php
    $title = match ($archiveType) {
        'category' => __('dixlase-blog::front.archive.category_title', ['name' => $archiveLabel]),
        'tag' => __('dixlase-blog::front.archive.tag_title', ['name' => $archiveLabel]),
        'date' => __('dixlase-blog::front.archive.date_title', ['date' => $archiveLabel]),
        'search' => $archiveLabel !== ''
            ? __('dixlase-blog::front.archive.search_title', ['query' => $archiveLabel])
            : __('dixlase-blog::front.archive.search_kicker'),
        default => $archiveLabel,
    };
@endphp

@section('title', $title)

@include('dixlase-blog::front._assets')

@section('content')
<div class="dixlase-blog">
    <section class="dixlase-blog-archive">
        <header class="dixlase-blog-archive__header">
            <p class="dixlase-blog-archive__kicker">
                @switch ($archiveType)
                    @case ('category')
                        {{ __('dixlase-blog::front.archive.category_kicker') }}
                        @break
                    @case ('tag')
                        {{ __('dixlase-blog::front.archive.tag_kicker') }}
                        @break
                    @case ('date')
                        {{ __('dixlase-blog::front.archive.date_kicker') }}
                        @break
                    @default
                        {{ __('dixlase-blog::front.archive.search_kicker') }}
                @endswitch
            </p>
            @if ($archiveLabel !== '')
                <h1 class="dixlase-blog-archive__heading" lang="{{ $archiveLang }}">{{ $archiveLabel }}</h1>
            @endif

            @if ($archiveType === 'search')
                <form class="dixlase-blog-search" role="search" method="GET" action="{{ route('dixlase-blog::front.search') }}">
                    <label class="dixlase-blog-search__label" for="dixlase-blog-search-q">{{ __('dixlase-blog::front.search.label') }}</label>
                    <input class="dixlase-blog-search__input" id="dixlase-blog-search-q" type="search" name="q"
                           value="{{ $archiveLabel }}" maxlength="100"
                           placeholder="{{ __('dixlase-blog::front.search.placeholder') }}">
                    <button class="dixlase-blog-search__button" type="submit">{{ __('dixlase-blog::front.search.button') }}</button>
                </form>
            @endif
        </header>

        @if ($posts->isEmpty())
            <p class="dixlase-blog-archive__empty">
                @if ($archiveType !== 'search')
                    {{ __('dixlase-blog::front.archive.no_posts') }}
                @elseif ($archiveLabel !== '')
                    {{ __('dixlase-blog::front.search.no_results') }}
                @endif
            </p>
        @else
            <div class="dixlase-blog-list">
                @foreach ($posts as $post)
                    @include('dixlase-blog::front._card', ['post' => $post])
                @endforeach
            </div>

            @include('dixlase-blog::front._pagination')
        @endif

        <p class="dixlase-blog-back">
            <a href="{{ route('dixlase-blog::front.index') }}">{{ __('dixlase-blog::front.archive.back_to_index') }}</a>
        </p>
    </section>
</div>
@endsection
