{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

One level of the categories widget; recursive.
--}}

<ul class="dixlase-blog-widget__list">
    @foreach ($items as $item)
        <li>
            <a href="{{ route('dixlase-blog::front.category', ['slug' => $item['category']->slug]) }}"
               lang="{{ $item['category']->localizedLang('label') }}">{{ $item['category']->localized('label') }}</a>
            <span class="dixlase-blog-widget__count">({{ $item['count'] }})</span>
            @if ($item['children'] !== [])
                @include('dixlase-blog::front.sidebar._category-items', ['items' => $item['children']])
            @endif
        </li>
    @endforeach
</ul>
