{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

The frame every blog settings screen extends: one form that sends only
that screen's fields, plus the screen's name so the save returns to it.
A screen fills the `fields` section.
--}}

@extends('layouts.admin')

@section('content')
    <form id="blog-settings-form" action="{{ route('dixlase-blog::admin.blog.settings.update') }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="screen" value="{{ $screen }}">

        @yield('fields')
    </form>
@endsection

@section('save')
    <x-admin.save-button
        id_confirmation="confirmBlogSettingsModal"
        :label="__('common.save')"
        :title="__('dixlase-blog::admin.settings.confirm_title')"
        :message="__('dixlase-blog::admin.settings.confirm_message')"
        :confirm_label="__('common.save')"
        :cancel_label="__('common.cancel')"
        form="blog-settings-form"
    />
@endsection
