{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Related posts under a post: those sharing a category or a tag.
Overridable.
--}}

@if ($relatedPosts->isNotEmpty())
    <section class="dixlase-blog-related">
        <h2 class="dixlase-blog-related__heading">{{ __('dixlase-blog::front.post.related') }}</h2>
        <ul class="dixlase-blog-related__list">
            @foreach ($relatedPosts as $related)
                <li class="dixlase-blog-related__item">
                    <a href="{{ route('dixlase-blog::front.show', ['slug' => $related->slug]) }}">
                        @if ($related->featuredMedia)
                            <img class="dixlase-blog-related__image" src="{{ asset('storage/media/'.$related->featuredMedia->path) }}" alt="" loading="lazy">
                        @endif
                        <span class="dixlase-blog-related__title" lang="{{ $related->localizedLang('title') }}">{{ $related->localized('title') }}</span>
                    </a>
                    @if ($related->published_at)
                        <time class="dixlase-blog-related__date" datetime="{{ $related->published_at->toIso8601String() }}">{{ $related->published_at->format('Y-m-d') }}</time>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
