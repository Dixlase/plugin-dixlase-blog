{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Public blog index. To override it, place a file with the same relative
path under custom/plugins/DixlaseBlog/resources/views/ (one site) or
themes/<theme>/plugins/DixlaseBlog/resources/views/ (one theme).
--}}
{{--
The blog's top-page listing, without a page around it.

Extracted from front/index.blade.php (#257) so the same markup serves both
the blog's own top page and a theme's front page body. It carries no
@extends and opens no @section: a theme places it inside its own layout.

$showHeading comes from the render context. A theme that already shows a
hero heading above the body passes false, and only the <h1> goes — the feed
link is still the one place a visitor finds the feed.

$cardColumns arrives from the view composer registered on this view in the
ServiceProvider. It is listed there as well as on front.index, because when
a provider renders this view directly no parent composer has run.
--}}

@php
    $showHeading ??= true;
@endphp

@component('dixlase-blog::front._layout', ['breadcrumbs' => $breadcrumbs ?? []])
    <section class="dixlase-blog-index">
        <header class="dixlase-blog-index__header">
            @if ($showHeading)
                <h1 class="dixlase-blog-index__heading">{{ __('dixlase-blog::front.index.heading') }}</h1>
            @endif
            <p class="dixlase-blog-index__feed">
                <a href="{{ route('dixlase-blog::front.feed') }}" type="application/rss+xml">{{ __('dixlase-blog::front.index.feed_link') }}</a>
            </p>
        </header>

        @if ($posts->isEmpty())
            <p class="dixlase-blog-index__empty">{{ __('dixlase-blog::front.index.no_posts') }}</p>
        @else
            @if (($fullPosts ?? null) !== null)
                {{-- The latest posts (index_layout = full): each with its full
                     text, or its excerpt and a link to the post. --}}
                <div class="dixlase-blog-full-list">
                    @foreach ($fullPosts as $item)
                        <article class="dixlase-blog-post dixlase-blog-full-list__post">
                            @include('dixlase-blog::front._post-article', [
                                'post' => $item['post'],
                                'contentHtml' => $item['contentHtml'],
                                'headingTag' => 'h2',
                                'titleUrl' => route('dixlase-blog::front.show', ['slug' => $item['post']->slug]),
                                'fields' => $postFields ?? null,
                            ])
                        </article>
                    @endforeach
                </div>
            @else
                <div @class(['dixlase-blog-list', 'dixlase-blog-list--single' => ($cardColumns ?? 'multi') === 'single'])>
                    @foreach ($posts as $post)
                        @include('dixlase-blog::front._card', ['post' => $post, 'showPinned' => true])
                    @endforeach
                </div>
            @endif

            @include('dixlase-blog::front._pagination')
        @endif
    </section>
@endcomponent
