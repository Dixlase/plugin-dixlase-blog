{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. You may use this file under either:

  (a) the GNU General Public License version 3 or later, as published
      by the Free Software Foundation; or

  (b) a commercial license agreement obtained from exc-D inc.
--}}

@extends('layouts.admin')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ __('dixlase-blog::admin.post_form.create_heading') }}
        </h1>
        <a href="{{ route('dixlase-blog::admin.blog.index') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
            <i class="fas fa-arrow-left"></i>
            {{ __('dixlase-blog::admin.post_form.back_to_posts') }}
        </a>
    </div>

    <form id="blog-post-form"
          action="{{ route('dixlase-blog::admin.blog.store') }}"
          method="POST">
        @include('dixlase-blog::admin.posts._form', [
            'post' => $post,
            'categoryTree' => $categoryTree,
            'selectedCategoryIds' => $selectedCategoryIds,
            'tagNames' => $tagNames,
        ])
    </form>
@endsection

@section('save')
    <x-admin.save-button
        id_confirmation="confirm-blog-post-save"
        :label="__('common.save')"
        :title="__('dixlase-blog::admin.post_form.save_confirm_title')"
        :message="__('dixlase-blog::admin.post_form.save_confirm_message')"
        :confirm_label="__('common.save')"
        :cancel_label="__('common.cancel')"
        form="blog-post-form"
    />
@endsection
