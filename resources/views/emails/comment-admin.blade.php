{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Mail to an admin about a new comment. What the commenter wrote goes
through BlogMailMarkdown::escape() so Markdown in it renders as plain
text.
--}}
<x-mail::message>
# {{ __('dixlase-blog::admin.comment_mail.heading') }}

{{ __('dixlase-blog::admin.comment_mail.intro', ['title' => \Plugins\DixlaseBlog\App\Support\BlogMailMarkdown::escape($post->title)]) }}

**{{ __('dixlase-blog::admin.comment_mail.status') }}**  
{{ $comment->isPublished() ? __('dixlase-blog::admin.comments.status_published') : __('dixlase-blog::admin.comments.status_pending') }}

**{{ __('dixlase-blog::admin.comment_mail.author') }}**  
{{ \Plugins\DixlaseBlog\App\Support\BlogMailMarkdown::escape($comment->author_name) }} ({{ \Plugins\DixlaseBlog\App\Support\BlogMailMarkdown::escape($comment->author_email) }})

**{{ __('dixlase-blog::admin.comment_mail.body') }}**

{{ \Plugins\DixlaseBlog\App\Support\BlogMailMarkdown::escape($comment->body) }}

<x-mail::button :url="$adminUrl">
{{ __('dixlase-blog::admin.comment_mail.button') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
