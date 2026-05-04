{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Categories index with inline-expansion forms (no modals).
Phase 6 will add drag-and-drop reordering on top of this layout.
--}}

@extends('layouts.admin')

@section('content')
    <div x-data="{ openId: null, creating: false }">

        <div class="mb-4 flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('dixlase-blog::admin.category.heading') }}
            </h1>
            <x-form-button
                type="button"
                icon="fas fa-plus"
                size="sm"
                variant="primary"
                @click="creating = !creating; openId = null"
            >
                {{ __('dixlase-blog::admin.category.new_button') }}
            </x-form-button>
        </div>

        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('dixlase-blog::admin.category.description') }}
        </p>

        {{-- Inline create form --}}
        <div x-show="creating" x-cloak
             class="mb-4 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 p-4">
            <form action="{{ route('dixlase-blog::admin.blog.categories.store') }}" method="POST" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <x-form-label :for="'create-label'" :label="__('dixlase-blog::admin.category.label_label')" />
                        <x-form-text id="create-label" name="label" :value="old('label')" required />
                        <x-form-error name="label" />
                    </div>
                    <div>
                        <x-form-label :for="'create-slug'" :label="__('dixlase-blog::admin.category.slug_label')" />
                        <x-form-text id="create-slug" name="slug" :value="old('slug')" class="font-mono" />
                        <x-form-help-text :text="__('dixlase-blog::admin.category.slug_help')" />
                        <x-form-error name="slug" />
                    </div>
                    <div>
                        <x-form-label :for="'create-parent_id'" :label="__('dixlase-blog::admin.category.parent_label')" />
                        <select id="create-parent_id" name="parent_id" class="input-common input-full">
                            <option value="">{{ __('dixlase-blog::admin.category.parent_none') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}"
                                    @selected(old('parent_id') == $cat->id)>
                                    {{ str_repeat('— ', $cat->depth) }}{{ $cat->label }}
                                </option>
                            @endforeach
                        </select>
                        <x-form-error name="parent_id" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <x-form-button type="submit" icon="fas fa-save" size="sm" variant="primary">
                        {{ __('dixlase-blog::admin.category.save') }}
                    </x-form-button>
                    <x-form-button type="button" size="sm" variant="secondary" @click="creating = false">
                        {{ __('dixlase-blog::admin.category.cancel') }}
                    </x-form-button>
                </div>
            </form>
        </div>

        {{-- Tree --}}
        @if ($categories->isEmpty())
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('dixlase-blog::admin.category.no_categories') }}
                </p>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.category.col_label') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.category.col_slug') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.category.col_posts') }}</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('dixlase-blog::admin.category.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($categories as $category)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100"
                                    style="padding-left: {{ ($category->depth * 1.5) + 1 }}rem;">
                                    @if ($category->depth > 0)
                                        <i class="fas fa-arrow-turn-down text-xs text-gray-400 mr-1"></i>
                                    @endif
                                    {{ $category->label }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $category->slug }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $category->posts_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-sm text-right whitespace-nowrap">
                                    <x-form-button
                                        type="button"
                                        icon="fas fa-pen"
                                        size="xs"
                                        variant="secondary"
                                        @click="openId = openId === {{ $category->id }} ? null : {{ $category->id }}; creating = false"
                                        :title="__('dixlase-blog::admin.category.edit')"
                                    />
                                    <form id="delete-cat-{{ $category->id }}"
                                          method="POST"
                                          action="{{ route('dixlase-blog::admin.blog.categories.destroy', ['category' => $category->id]) }}"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-form-button
                                            type="button"
                                            icon="fas fa-trash"
                                            size="xs"
                                            variant="danger"
                                            @click="openModal('delete-cat-modal-{{ $category->id }}')"
                                            :title="__('dixlase-blog::admin.category.delete')"
                                        />
                                    </form>
                                    <x-ui-modal
                                        :id="'delete-cat-modal-'.$category->id"
                                        :title="__('dixlase-blog::admin.category.delete_confirm_title')"
                                        :message="__('dixlase-blog::admin.category.delete_confirm_message')"
                                        :confirm_label="__('dixlase-blog::admin.category.delete')"
                                        :cancel_label="__('common.cancel')"
                                        icon_type="danger"
                                        confirm_color="red"
                                        :form="'delete-cat-'.$category->id"
                                    />
                                </td>
                            </tr>
                            {{-- Inline edit row --}}
                            <tr x-show="openId === {{ $category->id }}" x-cloak class="bg-blue-50 dark:bg-blue-900/20">
                                <td colspan="4" class="px-4 py-3">
                                    <form action="{{ route('dixlase-blog::admin.blog.categories.update', ['category' => $category->id]) }}"
                                          method="POST"
                                          class="space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <x-form-label :for="'edit-label-'.$category->id" :label="__('dixlase-blog::admin.category.label_label')" />
                                                <x-form-text :id="'edit-label-'.$category->id" name="label" :value="old('label', $category->label)" required />
                                                <x-form-error name="label" />
                                            </div>
                                            <div>
                                                <x-form-label :for="'edit-slug-'.$category->id" :label="__('dixlase-blog::admin.category.slug_label')" />
                                                <x-form-text :id="'edit-slug-'.$category->id" name="slug" :value="old('slug', $category->slug)" class="font-mono" />
                                                <x-form-error name="slug" />
                                            </div>
                                            <div>
                                                <x-form-label :for="'edit-parent_id-'.$category->id" :label="__('dixlase-blog::admin.category.parent_label')" />
                                                <select :id="'edit-parent_id-'.$category->id" name="parent_id" class="input-common input-full">
                                                    <option value="">{{ __('dixlase-blog::admin.category.parent_none') }}</option>
                                                    @foreach ($categories as $cat)
                                                        @if ($cat->id !== $category->id)
                                                            <option value="{{ $cat->id }}"
                                                                @selected(old('parent_id', $category->parent_id) == $cat->id)>
                                                                {{ str_repeat('— ', $cat->depth) }}{{ $cat->label }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <x-form-error name="parent_id" />
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <x-form-button type="submit" icon="fas fa-save" size="sm" variant="primary">
                                                {{ __('dixlase-blog::admin.category.save') }}
                                            </x-form-button>
                                            <x-form-button type="button" size="sm" variant="secondary" @click="openId = null">
                                                {{ __('dixlase-blog::admin.category.cancel') }}
                                            </x-form-button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
@endsection
