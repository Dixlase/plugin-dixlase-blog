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
        <x-form-button
            type="link"
            :href="route('dixlase-blog::admin.blog.index')"
            icon="fas fa-arrow-left"
            size="sm"
            variant="secondary"
        >
            {{ __('dixlase-blog::admin.trash.back_to_posts') }}
        </x-form-button>
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
        {{-- Same table as the post list. --}}
        <section class="admin-section !p-0 !border-0 !bg-transparent !dark:bg-transparent">
            <div class="admin-card">
                <div class="responsive-table">
                    <table class="admin-table" role="table" aria-label="{{ __('dixlase-blog::admin.trash.heading') }}">
                        <thead class="admin-table__head">
                            <tr class="admin-table__row">
                                <th class="admin-table__header" scope="col">ID</th>
                                <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.trash.col_title') }}</th>
                                <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.trash.col_deleted_at') }}</th>
                                <th class="admin-table__header admin-table__header--actions" scope="col">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="admin-table__body">
                            @foreach ($posts as $post)
                                <tr class="admin-table__row" role="row">
                                    <td class="admin-table__cell" data-label="ID" role="gridcell">
                                        <span class="font-mono text-sm">{{ $post->id }}</span>
                                    </td>
                                    <td class="admin-table__cell admin-table__cell--primary" data-label="{{ __('dixlase-blog::admin.trash.col_title') }}" role="gridcell">
                                        <div class="flex flex-col gap-1">
                                            <div class="admin-table__primary-content font-medium">{{ $post->title }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 font-mono break-all">{{ $post->slug }}</div>
                                        </div>
                                    </td>
                                    <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.trash.col_deleted_at') }}" role="gridcell">
                                        <time datetime="{{ $post->deleted_at?->toISOString() }}" class="admin-datetime">
                                            {{ $post->deleted_at?->format(__('dixlase-blog::admin.post_index.datetime_format')) ?? '—' }}
                                        </time>
                                    </td>
                                    <td class="admin-table__cell admin-table__cell--actions" data-label="{{ __('common.actions') }}" role="gridcell">
                                        <div class="flex items-center justify-end gap-2">
                                            <form id="restore-post-{{ $post->id }}"
                                                  method="POST"
                                                  action="{{ route('dixlase-blog::admin.blog.restore', ['id' => $post->id]) }}">
                                                @csrf
                                            </form>
                                            <x-form-button
                                                type="submit"
                                                variant="ghost"
                                                size="sm"
                                                icon="fas fa-rotate-left"
                                                form="restore-post-{{ $post->id }}"
                                                :title="__('dixlase-blog::admin.trash.restore')"
                                            />

                                            <form id="force-delete-{{ $post->id }}"
                                                  method="POST"
                                                  action="{{ route('dixlase-blog::admin.blog.forceDelete', ['id' => $post->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <x-form-button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                icon="fas fa-trash"
                                                class="!text-red-600 hover:!text-red-900 dark:!text-red-400 dark:hover:!text-red-300"
                                                :xClick="'openModal(\'force-delete-modal-' . $post->id . '\')'"
                                                :title="__('dixlase-blog::admin.trash.delete_forever')"
                                            />
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
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="mt-4">
            {{ $posts->links() }}
        </div>
    @endif
@endsection
