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

{{-- With the full text, each post's own CSS (HTML and Markdown; never JavaScript). --}}
@foreach ($fullPosts ?? [] as $item)
    @if ($item['customCssUrl'])
        @push('styles')
            <link rel="stylesheet" href="{{ $item['customCssUrl'] }}">
        @endpush
    @endif
@endforeach

@section('content')
{{-- The listing itself lives in _index-body, so the blog's own top page and a
     theme's front page body render the same markup (#257). --}}
@include('dixlase-blog::front._index-body', [
    'showHeading' => true,
    'breadcrumbs' => $breadcrumbs ?? [],
])
@endsection
