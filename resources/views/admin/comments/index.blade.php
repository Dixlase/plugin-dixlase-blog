{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Comments on every post, by state. The row checkboxes belong to the bulk
form above the table (form="comment-bulk-form"); each row button submits
its own small form, listed after the table.
--}}

@extends('layouts.admin')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ __('dixlase-blog::admin.comments.heading') }}
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {{ __('dixlase-blog::admin.comments.description') }}
        </p>
    </div>

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('dixlase-blog::admin.comments.tabs_label') }}">
        @foreach ($tabs as $item)
            <a href="{{ $item['url'] }}"
               class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium {{ $item['active'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' }}"
               @if ($item['active']) aria-current="page" @endif>
                {{ $item['label'] }}
                <span class="rounded-full px-2 text-xs {{ $item['active'] ? 'bg-white/20' : 'bg-white dark:bg-gray-800' }}">{{ $item['count'] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($comments->isEmpty())
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('dixlase-blog::admin.comments.none') }}</p>
        </div>
    @else
        <div x-data="blogCommentBulk">
            <form id="comment-bulk-form" method="POST" action="{{ route('dixlase-blog::admin.blog.comments.bulk') }}"
                  class="mb-3 flex flex-wrap items-center gap-2">
                @csrf
                <div class="w-56">
                    <x-form-select
                        id="comment-bulk-action"
                        name="action"
                        :options="['' => __('dixlase-blog::admin.comments.bulk_choose')] + $bulkActions"
                        value=""
                    />
                </div>
                <x-form-button type="submit" size="sm" variant="secondary" icon="fas fa-check">
                    {{ __('dixlase-blog::admin.comments.bulk_apply') }}
                </x-form-button>
                <x-form-error name="action" />
                <x-form-error name="ids" />
            </form>

            <section class="admin-section !p-0 !border-0 !bg-transparent !dark:bg-transparent">
                <div class="admin-card">
                    <div class="responsive-table">
                        <table class="admin-table" role="table" aria-label="{{ __('dixlase-blog::admin.comments.heading') }}">
                            <thead class="admin-table__head">
                                <tr class="admin-table__row">
                                    <th class="admin-table__header w-10" scope="col">
                                        <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600"
                                               x-ref="all" @change="toggleAll"
                                               aria-label="{{ __('dixlase-blog::admin.comments.select_all') }}">
                                    </th>
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_author') }}</th>
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_comment') }}</th>
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_status') }}</th>
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_date') }}</th>
                                    <th class="admin-table__header admin-table__header--actions" scope="col">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table__body">
                                @foreach ($rows as $row)
                                    <tr class="admin-table__row align-top" role="row">
                                        <td class="admin-table__cell" role="gridcell">
                                            <input type="checkbox" name="ids[]" value="{{ $row['id'] }}" form="comment-bulk-form"
                                                   data-comment-select class="rounded border-gray-300 dark:border-gray-600"
                                                   aria-label="{{ __('dixlase-blog::admin.comments.select_one', ['name' => $row['author']]) }}">
                                        </td>
                                        <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.comments.col_author') }}" role="gridcell">
                                            <div class="font-medium">
                                                {{ $row['author'] }}
                                                @if ($row['by_site'])
                                                    <span class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">{{ __('dixlase-blog::admin.comments.by_site') }}</span>
                                                @endif
                                            </div>
                                            @if ($row['email'])
                                                <div class="text-sm text-gray-500 dark:text-gray-400 break-all">{{ $row['email'] }}</div>
                                            @endif
                                            @if ($row['ip'])
                                                <div class="font-mono text-xs text-gray-400">{{ $row['ip'] }}</div>
                                            @endif
                                        </td>
                                        <td class="admin-table__cell admin-table__cell--primary" data-label="{{ __('dixlase-blog::admin.comments.col_comment') }}" role="gridcell">
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                @if ($row['post_edit_url'])
                                                    {{ __('dixlase-blog::admin.comments.on_post') }}
                                                    <a href="{{ $row['post_edit_url'] }}" class="hover:underline">{{ $row['post_title'] }}</a>
                                                    @if ($row['post_url'])
                                                        <a href="{{ $row['post_url'] }}" target="_blank" rel="noopener" class="ml-1 text-blue-600 dark:text-blue-400"
                                                           title="{{ __('dixlase-blog::admin.comments.view_on_site') }}"><i class="fas fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i></a>
                                                    @endif
                                                @endif
                                                @if ($row['parent_author'])
                                                    <span class="ml-1">{{ __('dixlase-blog::admin.comments.in_reply_to', ['name' => $row['parent_author']]) }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 whitespace-pre-line break-words text-gray-900 dark:text-gray-100">{{ $row['body'] }}</div>

                                            @if ($row['can_reply'])
                                                <details class="mt-2" @if ($errors->has('reply_body.'.$row['id'])) open @endif>
                                                    <summary class="cursor-pointer text-sm text-blue-600 dark:text-blue-400">{{ __('dixlase-blog::admin.comments.reply') }}</summary>
                                                    <form class="mt-2" method="POST" action="{{ route('dixlase-blog::admin.blog.comments.reply', ['comment' => $row['id']]) }}">
                                                        @csrf
                                                        <x-form-textarea
                                                            :id="'reply-body-'.$row['id']"
                                                            :name="'reply_body['.$row['id'].']'"
                                                            :rows="3"
                                                            :value="old('reply_body.'.$row['id'], '')"
                                                        />
                                                        <x-form-error :name="'reply_body.'.$row['id']" />
                                                        <x-form-button type="submit" size="sm" variant="primary" icon="fas fa-reply" class="mt-2">
                                                            {{ __('dixlase-blog::admin.comments.reply_submit') }}
                                                        </x-form-button>
                                                    </form>
                                                </details>
                                            @endif
                                        </td>
                                        <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.comments.col_status') }}" role="gridcell">
                                            <x-ui-status-badge :label="$row['status_label']" :variant="$row['status_variant']" />
                                        </td>
                                        <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.comments.col_date') }}" role="gridcell">
                                            <time datetime="{{ $row['date']?->toISOString() }}" class="admin-datetime">
                                                {{ $row['date']?->format(__('dixlase-blog::admin.post_index.datetime_format')) }}
                                            </time>
                                        </td>
                                        <td class="admin-table__cell admin-table__cell--actions" data-label="{{ __('common.actions') }}" role="gridcell">
                                            <div class="flex items-center justify-end gap-1">
                                                @if ($row['can_approve'])
                                                    <x-form-button type="submit" variant="ghost" size="sm" icon="fas fa-check"
                                                        class="!text-green-700 dark:!text-green-400"
                                                        :form="'comment-approve-'.$row['id']" :title="__('dixlase-blog::admin.comments.action_approve')" />
                                                @endif
                                                @if ($row['can_unpublish'])
                                                    <x-form-button type="submit" variant="ghost" size="sm" icon="fas fa-eye-slash"
                                                        :form="'comment-unpublish-'.$row['id']" :title="__('dixlase-blog::admin.comments.action_unpublish')" />
                                                @endif
                                                @if ($row['can_spam'])
                                                    <x-form-button type="submit" variant="ghost" size="sm" icon="fas fa-ban"
                                                        :form="'comment-spam-'.$row['id']" :title="__('dixlase-blog::admin.comments.action_spam')" />
                                                @endif
                                                @if ($row['can_trash'])
                                                    <x-form-button type="submit" variant="ghost" size="sm" icon="fas fa-trash"
                                                        class="!text-red-600 hover:!text-red-900 dark:!text-red-400 dark:hover:!text-red-300"
                                                        :form="'comment-trash-'.$row['id']" :title="__('dixlase-blog::admin.comments.action_trash')" />
                                                @endif
                                                @if ($row['can_restore'])
                                                    <x-form-button type="submit" variant="ghost" size="sm" icon="fas fa-rotate-left"
                                                        :form="'comment-restore-'.$row['id']" :title="__('dixlase-blog::admin.comments.action_restore')" />
                                                @endif
                                                @if ($row['can_delete'])
                                                    <x-form-button type="button" variant="ghost" size="sm" icon="fas fa-trash"
                                                        class="!text-red-600 hover:!text-red-900 dark:!text-red-400 dark:hover:!text-red-300"
                                                        :xClick="'openModal(\'comment-delete-modal-' . $row['id'] . '\')'"
                                                        :title="__('dixlase-blog::admin.comments.action_delete')" />
                                                    <x-ui-modal
                                                        :id="'comment-delete-modal-'.$row['id']"
                                                        :title="__('dixlase-blog::admin.comments.delete_confirm_title')"
                                                        :message="__('dixlase-blog::admin.comments.delete_confirm_message')"
                                                        :confirm_label="__('dixlase-blog::admin.comments.action_delete')"
                                                        :cancel_label="__('common.cancel')"
                                                        icon_type="danger"
                                                        confirm_color="red"
                                                        :form="'comment-delete-'.$row['id']"
                                                    />
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        {{-- The row buttons' forms. --}}
        <div hidden>
            @foreach ($rows as $row)
                @foreach (['approve' => 'published', 'unpublish' => 'pending', 'spam' => 'spam'] as $action => $status)
                    <form id="comment-{{ $action }}-{{ $row['id'] }}" method="POST" action="{{ route('dixlase-blog::admin.blog.comments.status', ['comment' => $row['id']]) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $status }}">
                    </form>
                @endforeach
                <form id="comment-trash-{{ $row['id'] }}" method="POST" action="{{ route('dixlase-blog::admin.blog.comments.destroy', ['comment' => $row['id']]) }}">@csrf</form>
                <form id="comment-restore-{{ $row['id'] }}" method="POST" action="{{ route('dixlase-blog::admin.blog.comments.restore', ['comment' => $row['id']]) }}">@csrf</form>
                <form id="comment-delete-{{ $row['id'] }}" method="POST" action="{{ route('dixlase-blog::admin.blog.comments.forceDelete', ['comment' => $row['id']]) }}">@csrf</form>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $comments->links() }}
        </div>
    @endif
@endsection
