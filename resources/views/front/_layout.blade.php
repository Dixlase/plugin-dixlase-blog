{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

The frame of every public blog page: the page's own content ($slot) and,
when the settings turn it on, the sidebar on the left or the right. Used
with @component('dixlase-blog::front._layout', ['breadcrumbs' => ...]).
The provider's view composer passes $sidebar (see BlogSidebar).
Overridable like the pages.
--}}

@php
    $sidebar ??= ['enabled' => false, 'position' => 'right', 'widgets' => []];
@endphp

<div @class([
    'dixlase-blog',
    'dixlase-blog--with-sidebar' => $sidebar['enabled'],
    'dixlase-blog--sidebar-left' => $sidebar['enabled'] && $sidebar['position'] === 'left',
])>
    <div class="dixlase-blog__main">
        @if (($breadcrumbs ?? []) !== [])
            @include('dixlase-blog::front._breadcrumbs', ['items' => $breadcrumbs])
        @endif
        {{ $slot }}
    </div>

    @if ($sidebar['enabled'])
        <aside class="dixlase-blog-sidebar" aria-label="{{ __('dixlase-blog::front.sidebar.label') }}">
            @foreach ($sidebar['widgets'] as $widget)
                @include('dixlase-blog::front.sidebar.'.$widget['key'], $widget['data'])
            @endforeach
        </aside>
    @endif
</div>
