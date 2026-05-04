{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.
--}}

@extends('layouts.admin')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ __('dixlase-blog::admin.trash.heading') }}
        </h1>
        <a href="{{ route('dixlase-blog::admin.blog.index') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
            <i class="fas fa-arrow-left"></i>
            {{ __('dixlase-blog::admin.trash.back_to_posts') }}
        </a>
    </div>

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('dixlase-blog::admin.trash.description') }}
    </p>

    @if ($posts->isEmpty())
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('dixlase-blog::admin.trash.no_posts') }}
            </p>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.trash.col_title') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.trash.col_deleted_at') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.trash.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($posts as $post)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                <div class="font-medium">{{ $post->title }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $post->slug }}</div>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $post->deleted_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right whitespace-nowrap">
                                <form method="POST"
                                      action="{{ route('dixlase-blog::admin.blog.restore', ['id' => $post->id]) }}"
                                      class="inline">
                                    @csrf
                                    <x-form-button
                                        type="submit"
                                        icon="fas fa-rotate-left"
                                        size="xs"
                                        variant="secondary"
                                    >
                                        {{ __('dixlase-blog::admin.trash.restore') }}
                                    </x-form-button>
                                </form>
                                <form id="force-delete-{{ $post->id }}"
                                      method="POST"
                                      action="{{ route('dixlase-blog::admin.blog.forceDelete', ['id' => $post->id]) }}"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-form-button
                                        type="button"
                                        icon="fas fa-trash"
                                        size="xs"
                                        variant="danger"
                                        @click="openModal('force-delete-modal-{{ $post->id }}')"
                                    >
                                        {{ __('dixlase-blog::admin.trash.delete_forever') }}
                                    </x-form-button>
                                </form>
                                <x-ui-modal
                                    :id="'force-delete-modal-'.$post->id"
                                    :title="__('dixlase-blog::admin.trash.delete_confirm_title')"
                                    :message="__('dixlase-blog::admin.trash.delete_confirm_message')"
                                    :confirm_label="__('dixlase-blog::admin.trash.delete_forever')"
                                    :cancel_label="__('common.cancel')"
                                    icon_type="danger"
                                    confirm_color="red"
                                    :form="'force-delete-'.$post->id"
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
