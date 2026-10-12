{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Sidebar widget: the latest visible posts. Overridable.
--}}

@if ($posts->isNotEmpty())
    <section class="dixlase-blog-widget dixlase-blog-widget--recent">
        <h2 class="dixlase-blog-widget__title">{{ __('dixlase-blog::front.sidebar.recent') }}</h2>
        <ul class="dixlase-blog-widget__list">
            @foreach ($posts as $post)
                <li>
                    <a href="{{ route('dixlase-blog::front.show', ['slug' => $post->slug]) }}"
                       lang="{{ $post->localizedLang('title') }}">{{ $post->localized('title') }}</a>
                    @if ($post->published_at)
                        <time class="dixlase-blog-widget__date" datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('Y-m-d') }}</time>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
