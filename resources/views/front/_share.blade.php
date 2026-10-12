{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Share buttons under a post (see BlogShareLinks). Each service link opens
its own share page in a new tab; the copy button is wired by
resources/src/js/front.js. Overridable.
--}}

@if ($shareLinks !== [])
    <div class="dixlase-blog-share">
        <span class="dixlase-blog-share__label">{{ __('dixlase-blog::front.share.label') }}</span>
        <ul class="dixlase-blog-share__list">
            @foreach ($shareLinks as $link)
                <li>
                    @if ($link['url'] !== null)
                        <a class="dixlase-blog-share__button dixlase-blog-share__button--{{ $link['key'] }}"
                           href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">{{ __('dixlase-blog::front.share.'.$link['key']) }}</a>
                    @else
                        <button type="button" class="dixlase-blog-share__button dixlase-blog-share__button--copy"
                                data-dixlase-blog-copy="{{ $postUrl }}"
                                data-copied-label="{{ __('dixlase-blog::front.share.copied') }}">{{ __('dixlase-blog::front.share.copy') }}</button>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
