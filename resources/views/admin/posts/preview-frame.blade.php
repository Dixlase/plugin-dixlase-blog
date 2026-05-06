{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Iframe shell view for the post editor's live preview pane. The page is
loaded by the iframe inside the editor's split-pane container; the
parent window (blogPostEditor Alpine component) posts
{ type: 'dixlase-preview-update', action: 'updateContent', html, title }
messages on every debounced edit, and this script swaps the title /
content nodes. Mirrors the protocol used by themes::admin.preview-frame
in DixlaseOnePage so preview infrastructure stays uniform across
plugins.
--}}

@extends('themes::layouts.preview')

@section('title', ' - ' . __('dixlase-blog::admin.post_form.preview_button'))

@section('content')
<article class="dixlase-blog-preview prose prose-lg dark:prose-invert mx-auto py-8 px-4 max-w-3xl">
    <h1 id="preview-title" class="dixlase-blog-preview__title">{{ $initialTitle ?? '' }}</h1>
    <div id="preview-content" class="dixlase-blog-preview__content">
        {!! $initialContent ?? '' !!}
    </div>
</article>
@endsection

@push('scripts')
<script @cspNonce>
/**
 * Preview iframe postMessage listener. Receives content updates from the
 * admin editor in the parent window and patches the live DOM in place so
 * the operator sees their unsaved Markdown / HTML rendered immediately.
 */
(function () {
    var titleEl = document.getElementById('preview-title');
    var contentEl = document.getElementById('preview-content');

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) return;
        if (!event.data || event.data.type !== 'dixlase-preview-update') return;
        if (event.data.action !== 'updateContent') return;

        if (titleEl && typeof event.data.title === 'string') {
            titleEl.textContent = event.data.title;
        }
        if (contentEl) {
            contentEl.innerHTML = event.data.html || '';
        }
    });

    // Tell the parent we are ready to accept updates so it can push the
    // current editor state on first load (first-paint sync).
    if (window.parent !== window) {
        window.parent.postMessage(
            { type: 'dixlase-preview-ready' },
            window.location.origin,
        );
    }

    // Echo our content height so previewMixin can size the iframe
    // without internal scroll. The same protocol DixlaseOnePage's
    // theme preview-frame uses; previewMixin in core listens for
    // "dixlase-preview-height" on the parent side.
    function postHeight() {
        if (window.parent === window) return;
        var h = document.documentElement.scrollHeight || document.body.scrollHeight || 0;
        window.parent.postMessage(
            { type: 'dixlase-preview-height', height: h },
            window.location.origin,
        );
    }
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(postHeight).observe(document.body);
    }
    window.addEventListener('load', postHeight);
    postHeight();
})();
</script>
@endpush
