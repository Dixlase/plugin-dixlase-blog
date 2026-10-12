{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Loads the blog's front styles into the theme's styles stack and links the
current locale's RSS feed. Included by every front page.
--}}

@push('styles')
    {!! load_plugin_assets('DixlaseBlog', ['css/style.scss', 'js/front.js']) !!}
    <link rel="alternate" type="application/rss+xml"
          title="{{ __('dixlase-blog::front.feed_title', ['site' => config('app.name')]) }}"
          href="{{ route('dixlase-blog::front.feed') }}">
@endpush
