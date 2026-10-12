{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Sidebar widget: a month calendar. Days with visible posts link to the
day archive; previous and next go to the nearest months with posts.
Overridable.
--}}

@php
    $locale = app()->getLocale();
    $sunday = $month->copy()->locale($locale)->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
@endphp

<section class="dixlase-blog-widget dixlase-blog-widget--calendar">
    <h2 class="dixlase-blog-widget__title">{{ __('dixlase-blog::front.sidebar.calendar') }}</h2>
    <table class="dixlase-blog-calendar">
        <caption>{{ $month->copy()->locale($locale)->isoFormat(__('dixlase-blog::front.archive.month_format')) }}</caption>
        <thead>
            <tr>
                @for ($i = 0; $i < 7; $i++)
                    @php $weekday = $sunday->copy()->addDays($i); @endphp
                    <th scope="col" abbr="{{ $weekday->dayName }}">{{ $weekday->minDayName }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($weeks as $week)
                <tr>
                    @foreach ($week as $cell)
                        @if ($cell === null)
                            <td></td>
                        @elseif ($cell['hasPosts'])
                            <td class="dixlase-blog-calendar__day--has-posts">
                                <a href="{{ route('dixlase-blog::front.day', ['year' => $month->year, 'month' => $month->format('m'), 'day' => sprintf('%02d', $cell['day'])]) }}">{{ $cell['day'] }}</a>
                            </td>
                        @else
                            <td>{{ $cell['day'] }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($previous || $next)
        <nav class="dixlase-blog-calendar__nav" aria-label="{{ __('dixlase-blog::front.sidebar.calendar') }}">
            @if ($previous)
                <a href="{{ route('dixlase-blog::front.month', ['year' => $previous->year, 'month' => $previous->format('m')]) }}" rel="prev">
                    ‹ {{ $previous->copy()->locale($locale)->isoFormat(__('dixlase-blog::front.archive.month_format')) }}
                </a>
            @endif
            @if ($next)
                <a href="{{ route('dixlase-blog::front.month', ['year' => $next->year, 'month' => $next->format('m')]) }}" rel="next">
                    {{ $next->copy()->locale($locale)->isoFormat(__('dixlase-blog::front.archive.month_format')) }} ›
                </a>
            @endif
        </nav>
    @endif
</section>
