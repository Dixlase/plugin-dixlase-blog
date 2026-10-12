{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Sidebar widget: categories with visible posts, nested, with counts.
Overridable; the nested list is _category-items.blade.php.
--}}

@if ($categories !== [])
    <section class="dixlase-blog-widget dixlase-blog-widget--categories">
        <h2 class="dixlase-blog-widget__title">{{ __('dixlase-blog::front.sidebar.categories') }}</h2>
        @include('dixlase-blog::front.sidebar._category-items', ['items' => $categories])
    </section>
@endif
