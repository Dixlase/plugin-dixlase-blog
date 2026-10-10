{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Categories index with drag-and-drop reordering (SortableJS via the
blog-category-reorder Alpine component) plus inline-expansion forms
for create / edit.

The outer x-data merges three concerns into a single Alpine scope:
  - blogCategoryReorder(...)  Sortable instances + persist endpoint
  - openId                    which row's inline edit form is open
  - creating                  whether the top-of-page create form is open
The single scope keeps the toolbar (saving / saved indicators next to
the New Category button) coherent without prop-drilling.
--}}

@extends('layouts.admin')

@section('content')
    <div x-data="{
            ...blogCategoryReorder({
                saveUrl: '{{ route('dixlase-blog::admin.blog.categories.reorder') }}',
                csrfToken: '{{ csrf_token() }}',
            }),
            openId: null,
            creating: false,
         }">

        <div class="mb-4 flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('dixlase-blog::admin.category.heading') }}
            </h1>

            <div class="flex items-center gap-3">
                {{-- Save status indicator --}}
                <span x-show="saving" x-cloak class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                    <i class="fas fa-spinner fa-spin"></i>
                    {{ __('dixlase-blog::admin.category.saving') }}
                </span>
                <span x-show="!saving && savedAt && !error" x-cloak class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                    <i class="fas fa-check"></i>
                    {{ __('dixlase-blog::admin.category.saved') }}
                </span>
                <span x-show="error" x-cloak class="inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span x-text="error"></span>
                </span>

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
        </div>

        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('dixlase-blog::admin.category.reorder_help') }}
        </p>

        {{-- Inline create form --}}
        <div x-show="creating" x-cloak
             class="mb-4 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 p-4">
            <form action="{{ route('dixlase-blog::admin.blog.categories.store') }}" method="POST" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <x-form-label :for="'create-label'" :text="__('dixlase-blog::admin.category.label_label')" />
                        <x-form-text id="create-label" name="label" :value="old('label')" required />
                        <x-form-error name="label" />
                    </div>
                    <div>
                        <x-form-label :for="'create-slug'" :text="__('dixlase-blog::admin.category.slug_label')" />
                        <x-form-text id="create-slug" name="slug" :value="old('slug')" class="font-mono" />
                        <x-form-help-text :text="__('dixlase-blog::admin.category.slug_help')" />
                        <x-form-error name="slug" />
                    </div>
                    <div>
                        <x-form-label :for="'create-parent_id'" :text="__('dixlase-blog::admin.category.parent_label')" />
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
                    {{-- Source language of the name; fixed once saved. Other
                         languages are added in the translation editor. --}}
                    @if ($multilingualEnabled)
                        <div>
                            <x-form-label :for="'create-lang'" :text="__('dixlase-blog::admin.category.lang_label')" />
                            <x-form-select id="create-lang" name="lang" :options="$languageOptions" :value="$langValue" />
                            <x-form-help-text :text="__('dixlase-blog::admin.category.lang_help')" />
                            <x-form-error name="lang" />
                        </div>
                    @endif
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
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-3">
                <ul class="category-tree list-none min-h-4 space-y-0" data-parent-id="">
                    @foreach ($categories->whereNull('parent_id') as $root)
                        @include('dixlase-blog::admin.categories._tree-node', [
                            'category' => $root,
                            'allCategories' => $categories,
                        ])
                    @endforeach
                </ul>
            </div>
        @endif

    </div>
@endsection
