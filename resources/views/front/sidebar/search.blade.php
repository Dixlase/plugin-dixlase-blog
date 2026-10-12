{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Sidebar widget: blog search. Overridable.
--}}

<section class="dixlase-blog-widget dixlase-blog-widget--search">
    <h2 class="dixlase-blog-widget__title">{{ __('dixlase-blog::front.sidebar.search') }}</h2>
    <form class="dixlase-blog-search" role="search" method="GET" action="{{ route('dixlase-blog::front.search') }}">
        <label class="dixlase-blog-search__label" for="dixlase-blog-widget-search-q">{{ __('dixlase-blog::front.search.label') }}</label>
        <input class="dixlase-blog-search__input" id="dixlase-blog-widget-search-q" type="search" name="q"
               value="{{ $query }}" maxlength="100"
               placeholder="{{ __('dixlase-blog::front.search.placeholder') }}">
        <button class="dixlase-blog-search__button" type="submit">{{ __('dixlase-blog::front.search.button') }}</button>
    </form>
</section>
