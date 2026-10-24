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
        {{-- Same table as the DixlasePages page list. --}}
        <section class="admin-section !p-0 !border-0 !bg-transparent !dark:bg-transparent">
            <div class="admin-card">
                <div class="responsive-table">
                    <table class="admin-table" role="table" aria-label="{{ __('dixlase-blog::admin.post_index.heading') }}">
                        <thead class="admin-table__head">
                            <tr class="admin-table__row">
                                <th class="admin-table__header" scope="col">ID</th>
                                <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.post_index.col_title') }}</th>
                                <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.post_index.col_status') }}</th>
                                <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.post_index.col_categories') }}</th>
                                <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.post_index.col_comments') }}</th>
                                <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.post_index.col_published_at') }}</th>
                                <th class="admin-table__header admin-table__header--actions" scope="col">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="admin-table__body">
                            @foreach ($posts as $post)
                                <tr class="admin-table__row" role="row">
                                    <td class="admin-table__cell" data-label="ID" role="gridcell">
                                        <span class="font-mono text-sm">{{ $post->id }}</span>
                                    </td>
                                    <td class="admin-table__cell admin-table__cell--primary" data-label="{{ __('dixlase-blog::admin.post_index.col_title') }}" role="gridcell">
                                        <div class="flex flex-col gap-1">
                                            <div class="admin-table__primary-content">
                                                <a href="{{ route('dixlase-blog::admin.blog.edit', ['post' => $post->id]) }}" class="hover:underline font-medium">
                                                    {{ $post->title }}
                                                </a>
                                                @if ($post->is_pinned)
                                                    <i class="fas fa-thumbtack ml-1 text-xs text-gray-500 dark:text-gray-400"
                                                       title="{{ __('dixlase-blog::admin.post.pinned_label') }}"
                                                       aria-label="{{ __('dixlase-blog::admin.post.pinned_label') }}"></i>
                                                @endif
                                            </div>
                                            {{-- The permalink ends with the slug, so the slug is not shown
                                                 apart. It is a link only while the post is public. --}}
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                @if ($post->isCurrentlyVisible())
                                                    <a href="{{ $permalinks[$post->id] }}" target="_blank" rel="noopener"
                                                       class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline break-all">
                                                        {{ $permalinks[$post->id] }}
                                                    </a>
                                                @else
                                                    <span class="break-all" title="{{ __('dixlase-blog::admin.post_index.permalink_not_public') }}">{{ $permalinks[$post->id] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.post_index.col_status') }}" role="gridcell">
                                        @include('components::ui-status-badge', [
                                            'status' => $post->status->slug(),
                                            'label' => $post->status->label(),
                                            'variant' => $post->status->cssClass(),
                                        ])
                                    </td>
                                    <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.post_index.col_categories') }}" role="gridcell">
                                        @forelse ($post->categories as $category)
                                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 mr-1">
                                                {{ $category->label }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-400">—</span>
                                        @endforelse
                                    </td>
                                    <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.post_index.col_comments') }}" role="gridcell">
                                        {{-- Published, then the ones waiting for approval, which
                                             link to the comments screen's "Pending" tab. --}}
                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                            <span class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-300"
                                                  title="{{ __('dixlase-blog::admin.post_index.comments_published', ['count' => $post->published_comments_count]) }}">
                                                <i class="fas fa-comment text-gray-400" aria-hidden="true"></i>
                                                <span class="sr-only">{{ __('dixlase-blog::admin.post_index.comments_published', ['count' => $post->published_comments_count]) }}</span>
                                                <span aria-hidden="true">{{ $post->published_comments_count }}</span>
                                            </span>
                                            @if ($post->pending_comments_count > 0)
                                                <a href="{{ $pendingCommentsUrl }}"
                                                   class="inline-flex items-center rounded px-2 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 hover:underline">
                                                    {{ __('dixlase-blog::admin.post_index.comments_pending', ['count' => $post->pending_comments_count]) }}
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.post_index.col_published_at') }}" role="gridcell">
                                        <x-ui-datetime
                                            :value="$post->published_at"
                                            :format="__('dixlase-blog::admin.post_index.datetime_format')"
                                            empty-label="—"
                                            class="admin-datetime"
                                        />
                                    </td>
                                    <td class="admin-table__cell admin-table__cell--actions" data-label="{{ __('common.actions') }}" role="gridcell">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-form-button
                                                type="link"
                                                variant="ghost"
                                                size="sm"
                                                icon="fas fa-edit"
                                                :href="route('dixlase-blog::admin.blog.edit', ['post' => $post->id])"
                                                :title="__('dixlase-blog::admin.post_index.edit')"
                                            />

                                            <form id="delete-post-{{ $post->id }}"
                                                  method="POST"
                                                  action="{{ route('dixlase-blog::admin.blog.destroy', ['post' => $post->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <x-form-button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                icon="fas fa-trash"
                                                class="!text-red-600 hover:!text-red-900 dark:!text-red-400 dark:hover:!text-red-300"
                                                :xClick="'openModal(\'delete-post-modal-' . $post->id . '\')'"
                                                :title="__('dixlase-blog::admin.post_index.delete')"
                                            />
                                            <x-ui-modal
                                                :id="'delete-post-modal-'.$post->id"
                                                :title="__('dixlase-blog::admin.post_index.delete_confirm_title')"
                                                :message="__('dixlase-blog::admin.post_index.delete_confirm_message')"
                                                :confirm_label="__('dixlase-blog::admin.post_index.delete')"
                                                :cancel_label="__('common.cancel')"
                                                icon_type="warning"
                                                confirm_color="amber"
                                                :form="'delete-post-'.$post->id"
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
