{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.
--}}

@extends('layouts.admin')

@section('content')
    <h1 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
        {{ __('dixlase-blog::admin.revision.heading') }} — {{ $post->title }}
    </h1>

    <x-revision.list
        :revisions="$revisions"
        :typeLabels="$typeLabels"
        :retention="$retention"
        :protectedCount="$protectedCount"
        :backRoute="route('dixlase-blog::admin.blog.edit', ['post' => $post->id])"
        showRouteName="dixlase-blog::admin.blog.revisions.show"
        restoreRouteName="dixlase-blog::admin.blog.revisions.restore"
        protectRouteName="dixlase-blog::admin.blog.revisions.protect"
        :parentParams="[$post->id]"
        translationPrefix="dixlase-blog::admin.revision"
    />
@endsection
