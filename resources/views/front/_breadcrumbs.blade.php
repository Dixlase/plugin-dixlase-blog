{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Breadcrumbs above a blog page (see BlogBreadcrumbs), with the same trail
as BreadcrumbList structured data. Overridable.
--}}

@php
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(fn (array $item, int $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item['label'],
            'item' => $item['url'],
        ], $items, array_keys($items)),
    ];
@endphp

<nav class="dixlase-blog-breadcrumbs" aria-label="{{ __('dixlase-blog::front.breadcrumbs.label') }}">
    <ol class="dixlase-blog-breadcrumbs__list">
        @foreach ($items as $item)
            <li class="dixlase-blog-breadcrumbs__item">
                @if ($loop->last)
                    <span aria-current="page" lang="{{ $item['lang'] }}">{{ $item['label'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" lang="{{ $item['lang'] }}">{{ $item['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
<script type="application/ld+json" @cspNonce>{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
