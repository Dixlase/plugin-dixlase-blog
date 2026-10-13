{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

One published comment and, below it, its replies; includes itself for
each reply, to any depth. The body is plain text: escaped, with its line
breaks kept. Overridable.

$node: ['comment' => BlogComment, 'replies' => list of nodes]
$depth: 0 for a comment on the post
--}}

<li class="dixlase-blog-comment{{ $node['comment']->isBySite() ? ' dixlase-blog-comment--site' : '' }}" id="comment-{{ $node['comment']->id }}">
    <div class="dixlase-blog-comment__header">
        <span class="dixlase-blog-comment__author">{{ $node['comment']->author_name }}</span>
        @if ($node['comment']->isBySite())
            <span class="dixlase-blog-comment__badge">{{ __('dixlase-blog::front.comments.site_badge') }}</span>
        @endif
        <time class="dixlase-blog-comment__date" datetime="{{ $node['comment']->created_at?->toIso8601String() }}">{{ $node['comment']->created_at?->format('Y-m-d H:i') }}</time>
    </div>
    <div class="dixlase-blog-comment__body"@if ($node['comment']->lang) lang="{{ $node['comment']->lang }}"@endif>{!! nl2br(e($node['comment']->body), false) !!}</div>
    @if ($commentsOpen)
        <a class="dixlase-blog-comment__reply" href="{{ $postUrl }}?replyto={{ $node['comment']->id }}#comment-form">{{ __('dixlase-blog::front.comments.reply') }}</a>
    @endif

    @if ($node['replies'] !== [])
        {{-- The indent stops growing after a few levels; see style.scss. --}}
        <ol class="dixlase-blog-comments__replies{{ $depth >= 2 ? ' dixlase-blog-comments__replies--deep' : '' }}{{ $depth >= 5 ? ' dixlase-blog-comments__replies--deeper' : '' }}">
            @foreach ($node['replies'] as $reply)
                @include('dixlase-blog::front._comment', ['node' => $reply, 'depth' => $depth + 1])
            @endforeach
        </ol>
    @endif
</li>
