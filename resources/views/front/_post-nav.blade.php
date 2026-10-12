{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Previous and next posts under a post. Overridable.
--}}

@if ($previousPost || $nextPost)
    <nav class="dixlase-blog-post-nav" aria-label="{{ __('dixlase-blog::front.post.nav_label') }}">
        @if ($previousPost)
            <a class="dixlase-blog-post-nav__link dixlase-blog-post-nav__link--previous" rel="prev"
               href="{{ route('dixlase-blog::front.show', ['slug' => $previousPost->slug]) }}">
                <span class="dixlase-blog-post-nav__label">{{ __('dixlase-blog::front.post.previous') }}</span>
                <span class="dixlase-blog-post-nav__title" lang="{{ $previousPost->localizedLang('title') }}">{{ $previousPost->localized('title') }}</span>
            </a>
        @endif
        @if ($nextPost)
            <a class="dixlase-blog-post-nav__link dixlase-blog-post-nav__link--next" rel="next"
               href="{{ route('dixlase-blog::front.show', ['slug' => $nextPost->slug]) }}">
                <span class="dixlase-blog-post-nav__label">{{ __('dixlase-blog::front.post.next') }}</span>
                <span class="dixlase-blog-post-nav__title" lang="{{ $nextPost->localizedLang('title') }}">{{ $nextPost->localized('title') }}</span>
            </a>
        @endif
    </nav>
@endif
