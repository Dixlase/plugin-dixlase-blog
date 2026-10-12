{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Sidebar widget: the most used tags. Overridable.
--}}

@if ($tags->isNotEmpty())
    <section class="dixlase-blog-widget dixlase-blog-widget--tags">
        <h2 class="dixlase-blog-widget__title">{{ __('dixlase-blog::front.sidebar.tags') }}</h2>
        <div class="dixlase-blog-widget__chips">
            @foreach ($tags as $tag)
                <a class="dixlase-blog-chip dixlase-blog-chip--tag"
                   href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}"
                   lang="{{ $tag->localizedLang('name') }}">#{{ $tag->localized('name') }} <span class="dixlase-blog-widget__count">({{ $tag->posts_count }})</span></a>
            @endforeach
        </div>
    </section>
@endif
