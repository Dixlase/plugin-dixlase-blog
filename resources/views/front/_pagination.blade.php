{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Previous / next links for a paginated post list ($posts). The URLs keep
the current path, so they stay under the current locale prefix.
--}}

@if ($posts->hasPages())
    <nav class="dixlase-blog-pagination" aria-label="{{ __('dixlase-blog::front.pagination.label') }}">
        @if ($posts->onFirstPage())
            <span class="dixlase-blog-pagination__link is-disabled" aria-disabled="true">{{ __('dixlase-blog::front.pagination.previous') }}</span>
        @else
            <a class="dixlase-blog-pagination__link" href="{{ $posts->previousPageUrl() }}" rel="prev">{{ __('dixlase-blog::front.pagination.previous') }}</a>
        @endif

        <span class="dixlase-blog-pagination__status">
            {{ __('dixlase-blog::front.pagination.status', ['current' => $posts->currentPage(), 'last' => $posts->lastPage()]) }}
        </span>

        @if ($posts->hasMorePages())
            <a class="dixlase-blog-pagination__link" href="{{ $posts->nextPageUrl() }}" rel="next">{{ __('dixlase-blog::front.pagination.next') }}</a>
        @else
            <span class="dixlase-blog-pagination__link is-disabled" aria-disabled="true">{{ __('dixlase-blog::front.pagination.next') }}</span>
        @endif
    </nav>
@endif
