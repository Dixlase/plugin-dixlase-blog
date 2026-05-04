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

    <x-revision.diff
        :revision="$revision"
        :typeLabels="$typeLabels"
        :diffs="$diffs"
        :metaDiffs="$metaDiffs"
        :hasChanges="$hasChanges"
        :backRoute="route('dixlase-blog::admin.blog.revisions.index', ['post' => $post->id])"
        restoreRouteName="dixlase-blog::admin.blog.revisions.restore"
        noteRouteName="dixlase-blog::admin.blog.revisions.note"
        protectRouteName="dixlase-blog::admin.blog.revisions.protect"
        :parentParams="[$post->id]"
        translationPrefix="dixlase-blog::admin.revision"
    />

    {{-- Pivot diffs (categories / tags) — not handled by core x-revision.diff --}}
    @if (! empty($pivotDiffs))
        <section class="mt-6">
            <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ __('dixlase-blog::admin.revision.pivot_heading') }}
            </h2>
            <table class="min-w-full text-sm border border-gray-200 dark:border-gray-700 rounded">
                <thead>
                    <tr class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800">
                        <th class="px-3 py-1 text-left font-medium"></th>
                        <th class="px-3 py-1 text-left font-medium">{{ __('dixlase-blog::admin.revision.pivot_added') }}</th>
                        <th class="px-3 py-1 text-left font-medium">{{ __('dixlase-blog::admin.revision.pivot_removed') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pivotDiffs as $field => $values)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="px-3 py-1 font-medium text-gray-700 dark:text-gray-200">
                                {{ __('dixlase-blog::admin.revision.diff_field_'.$field) }}
                            </td>
                            <td class="px-3 py-1 text-green-700 dark:text-green-400">
                                {{ empty($values['added']) ? '—' : implode(', ', $values['added']) }}
                            </td>
                            <td class="px-3 py-1 text-red-700 dark:text-red-400">
                                {{ empty($values['removed']) ? '—' : implode(', ', $values['removed']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
