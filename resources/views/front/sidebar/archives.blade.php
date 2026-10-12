{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Sidebar widget: months with visible posts, newest first. Overridable.
--}}

@if ($months !== [])
    <section class="dixlase-blog-widget dixlase-blog-widget--archives">
        <h2 class="dixlase-blog-widget__title">{{ __('dixlase-blog::front.sidebar.archives') }}</h2>
        <ul class="dixlase-blog-widget__list">
            @foreach ($months as $month)
                <li>
                    <a href="{{ route('dixlase-blog::front.month', ['year' => $month['year'], 'month' => sprintf('%02d', $month['month'])]) }}">
                        {{ \Illuminate\Support\Carbon::create($month['year'], $month['month'], 1)->locale(app()->getLocale())->isoFormat(__('dixlase-blog::front.archive.month_format')) }}
                    </a>
                    <span class="dixlase-blog-widget__count">({{ $month['count'] }})</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
