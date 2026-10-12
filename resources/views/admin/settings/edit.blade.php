{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Blog settings. Later sections (sidebar, comments, sharing) add their own
<section> blocks to this one form.
--}}

@extends('layouts.admin')

@section('content')
    <form id="blog-settings-form" action="{{ route('dixlase-blog::admin.blog.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <section class="mb-8">
            <h2 class="mb-4 text-xl font-semibold">{{ __('dixlase-blog::admin.settings.listing_heading') }}</h2>

            <div class="grid grid-cols-1 gap-6">
                <div class="max-w-xs">
                    <x-form-label
                        for="posts_per_page"
                        :text="__('dixlase-blog::admin.settings.posts_per_page_label')"
                        :required="true"
                    />
                    <x-form-text
                        id="posts_per_page"
                        name="posts_per_page"
                        type="number"
                        :min="$postsPerPageMin"
                        :max="$postsPerPageMax"
                        :value="old('posts_per_page', $blogSettings['posts_per_page'])"
                        :required="true"
                    />
                    <x-form-help-text :text="__('dixlase-blog::admin.settings.posts_per_page_help', ['min' => $postsPerPageMin, 'max' => $postsPerPageMax])" />
                    <x-form-error name="posts_per_page" />
                </div>
            </div>
        </section>
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
