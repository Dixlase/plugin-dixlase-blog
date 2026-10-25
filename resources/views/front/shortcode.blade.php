{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

The [blog] shortcode (BlogShortcode): the latest posts inside other
content, such as the front page. $layout is cards, list or full; $showLink
adds a link to the blog's top page. Overridable like index.blade.php.
Titles are h3: the content around it carries the section's own heading.
--}}

@include('dixlase-blog::front._assets')

{{-- In full, each post's own CSS, as on the top page (never JavaScript). --}}
@foreach ($fullPosts as $item)
    @if ($item['customCssUrl'])
        @push('styles')
            <link rel="stylesheet" href="{{ $item['customCssUrl'] }}">
        @endpush
    @endif
@endforeach

<div class="dixlase-blog dixlase-blog-embed dixlase-blog-embed--{{ $layout }}">
    @if ($layout === 'full')
        <div class="dixlase-blog-full-list">
            @foreach ($fullPosts as $item)
                <article class="dixlase-blog-post dixlase-blog-full-list__post">
                    @include('dixlase-blog::front._post-article', [
                        'post' => $item['post'],
                        'contentHtml' => $item['contentHtml'],
                        'headingTag' => 'h3',
                        'titleUrl' => route('dixlase-blog::front.show', ['slug' => $item['post']->slug]),
                    ])
                </article>
            @endforeach
        </div>
    @elseif ($layout === 'list')
        <ul class="dixlase-blog-embed__list">
            @foreach ($posts as $post)
                <li class="dixlase-blog-embed__item">
                    @if ($post->published_at)
                        <time class="dixlase-blog-embed__date" datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
                    @endif
                    <a class="dixlase-blog-embed__title" href="{{ route('dixlase-blog::front.show', ['slug' => $post->slug]) }}"
                       lang="{{ $post->localizedLang('title') }}">{{ $post->localized('title') }}</a>
                </li>
            @endforeach
        </ul>
    @else
        <div class="dixlase-blog-list">
            @foreach ($posts as $post)
                @include('dixlase-blog::front._card', ['post' => $post, 'headingTag' => 'h3'])
            @endforeach
        </div>
    @endif

    @if ($showLink)
        <p class="dixlase-blog-embed__more">
            <a href="{{ route('dixlase-blog::front.index') }}">{{ __('dixlase-blog::front.shortcode.more') }}</a>
        </p>
    @endif
</div>
