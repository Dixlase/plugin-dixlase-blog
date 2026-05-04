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
            {{ __('dixlase-blog::admin.post_index.heading') }}
        </h1>
        <div class="flex items-center gap-2">
            <x-form-button
                type="link"
                :href="route('dixlase-blog::admin.blog.trash')"
                icon="fas fa-trash-can"
                size="sm"
                variant="secondary"
            >
                {{ __('dixlase-blog::admin.post_index.trash_link') }}
            </x-form-button>
            <x-form-button
                type="link"
                :href="route('dixlase-blog::admin.blog.create')"
                icon="fas fa-plus"
                size="sm"
                variant="primary"
            >
                {{ __('dixlase-blog::admin.post_index.new_button') }}
            </x-form-button>
        </div>
    </div>

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('dixlase-blog::admin.post_index.description') }}
    </p>

    @if ($posts->isEmpty())
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('dixlase-blog::admin.post_index.no_posts') }}
            </p>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.post_index.col_title') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.post_index.col_status') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.post_index.col_categories') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.post_index.col_published_at') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.post_index.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($posts as $post)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                <a href="{{ route('dixlase-blog::admin.blog.edit', ['post' => $post->id]) }}"
                                   class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ $post->title }}
                                </a>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $post->slug }}</div>
                            </td>
                            <td class="px-4 py-2 text-sm whitespace-nowrap">
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $post->status?->cssClass() ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $post->status ? __($post->status->translationKey()) : '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                @forelse ($post->categories as $category)
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 mr-1">
                                        {{ $category->label }}
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $post->published_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right whitespace-nowrap">
                                <x-form-button
                                    type="link"
                                    :href="route('dixlase-blog::admin.blog.edit', ['post' => $post->id])"
                                    icon="fas fa-pen"
                                    size="xs"
                                    variant="secondary"
                                    :title="__('dixlase-blog::admin.post_index.edit')"
                                />
                                <form id="delete-post-{{ $post->id }}"
                                      method="POST"
                                      action="{{ route('dixlase-blog::admin.blog.destroy', ['post' => $post->id]) }}"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-form-button
                                        type="button"
                                        icon="fas fa-trash"
                                        size="xs"
                                        variant="danger"
                                        :title="__('dixlase-blog::admin.post_index.delete')"
                                        @click="openModal('delete-post-modal-{{ $post->id }}')"
                                    />
                                </form>
                                <x-ui-modal
                                    :id="'delete-post-modal-'.$post->id"
                                    :title="__('dixlase-blog::admin.post_index.delete_confirm_title')"
                                    :message="__('dixlase-blog::admin.post_index.delete_confirm_message')"
                                    :confirm_label="__('dixlase-blog::admin.post_index.delete')"
                                    :cancel_label="__('common.cancel')"
                                    icon_type="danger"
                                    confirm_color="red"
                                    :form="'delete-post-'.$post->id"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $posts->links() }}
        </div>
    @endif
@endsection
