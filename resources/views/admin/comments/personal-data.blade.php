{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Commenters' personal data, by e-mail address: find the comments, then
remove the personal data from them or delete them.
--}}

@extends('layouts.admin')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ __('dixlase-blog::admin.personal_data.heading') }}
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {{ __('dixlase-blog::admin.personal_data.description') }}
        </p>
    </div>

    <form method="GET" action="{{ route('dixlase-blog::admin.blog.comments.personalData.index') }}" class="mb-6 flex max-w-xl flex-wrap items-end gap-2">
        <div class="flex-1">
            <x-form-label for="personal-data-email" :text="__('dixlase-blog::admin.personal_data.email_label')" />
            <x-form-text id="personal-data-email" name="email" type="email" :value="$email" :required="true" />
        </div>
        <x-form-button type="submit" size="md" variant="secondary" icon="fas fa-magnifying-glass" class="mb-2">
            {{ __('dixlase-blog::admin.personal_data.find') }}
        </x-form-button>
    </form>

    @if ($emailInvalid)
        <x-form-error :messages="[__('dixlase-blog::admin.personal_data.email_invalid')]" />
    @endif

    @if ($searched)
        @if ($found === [])
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('dixlase-blog::admin.personal_data.none', ['email' => $email]) }}</p>
            </div>
        @else
            <p class="mb-3 text-sm text-gray-700 dark:text-gray-300">
                {{ trans_choice('dixlase-blog::admin.personal_data.found', count($found), ['count' => count($found), 'email' => $email]) }}
            </p>

            <section class="admin-section !p-0 !border-0 !bg-transparent !dark:bg-transparent mb-6">
                <div class="admin-card">
                    <div class="responsive-table">
                        <table class="admin-table" role="table" aria-label="{{ __('dixlase-blog::admin.personal_data.heading') }}">
                            <thead class="admin-table__head">
                                <tr class="admin-table__row">
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_author') }}</th>
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_comment') }}</th>
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_status') }}</th>
                                    <th class="admin-table__header" scope="col">{{ __('dixlase-blog::admin.comments.col_date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table__body">
                                @foreach ($found as $row)
                                    <tr class="admin-table__row" role="row">
                                        <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.comments.col_author') }}" role="gridcell">{{ $row['author'] }}</td>
                                        <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.comments.col_comment') }}" role="gridcell">
                                            @if ($row['post_title'])
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.comments.on_post') }} {{ $row['post_title'] }}</div>
                                            @endif
                                            <div>{{ $row['excerpt'] }}</div>
                                        </td>
                                        <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.comments.col_status') }}" role="gridcell">{{ $row['status_label'] }}</td>
                                        <td class="admin-table__cell" data-label="{{ __('dixlase-blog::admin.comments.col_date') }}" role="gridcell">
                                            <time datetime="{{ $row['date']?->toISOString() }}" class="admin-datetime">{{ $row['date']?->format(__('dixlase-blog::admin.post_index.datetime_format')) }}</time>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap gap-2">
                @foreach (['anonymise' => 'amber', 'delete' => 'red'] as $mode => $color)
                    <form id="personal-data-{{ $mode }}" method="POST" action="{{ route('dixlase-blog::admin.blog.comments.personalData.erase') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <input type="hidden" name="mode" value="{{ $mode }}">
                    </form>
                    <x-form-button type="button" size="sm" :variant="$mode === 'delete' ? 'danger' : 'warning'"
                        :icon="$mode === 'delete' ? 'fas fa-trash' : 'fas fa-user-slash'"
                        :xClick="'openModal(\'personal-data-' . $mode . '-modal\')'">
                        {{ __('dixlase-blog::admin.personal_data.'.$mode) }}
                    </x-form-button>
                    <x-ui-modal
                        :id="'personal-data-'.$mode.'-modal'"
                        :title="__('dixlase-blog::admin.personal_data.'.$mode.'_confirm_title')"
                        :message="__('dixlase-blog::admin.personal_data.'.$mode.'_confirm_message', ['email' => $email])"
                        :confirm_label="__('dixlase-blog::admin.personal_data.'.$mode)"
                        :cancel_label="__('common.cancel')"
                        icon_type="warning"
                        :confirm_color="$color"
                        :form="'personal-data-'.$mode"
                    />
                @endforeach
            </div>
        @endif
    @endif
@endsection
