{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

RSS 2.0 feed (excerpts only — full content is intentionally not
included; readers are expected to follow the link to the site).
--}}<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>{{ __('dixlase-blog::front.feed_title', ['site' => $siteName]) }}</title>
        <link>{{ $blogUrl }}</link>
        <description>{{ __('dixlase-blog::front.feed_description', ['site' => $siteName]) }}</description>
        <language>{{ $language }}</language>
        <atom:link href="{{ $feedUrl }}" rel="self" type="application/rss+xml" />
        @if ($posts->isNotEmpty() && $posts->first()->published_at)
        <lastBuildDate>{{ $posts->first()->published_at->toRfc2822String() }}</lastBuildDate>
        @endif
        @foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('dixlase-blog::front.show', ['slug' => $post->slug]) }}</link>
            <guid isPermaLink="true">{{ route('dixlase-blog::front.show', ['slug' => $post->slug]) }}</guid>
            @if ($post->published_at)
            <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
            @endif
            @if ($post->author)
            <dc:creator>{{ $post->author->display_name ?? $post->author->account_name }}</dc:creator>
            @endif
            <description><![CDATA[{!! $post->excerpt ?? '' !!}]]></description>
        </item>
        @endforeach
    </channel>
</rss>
