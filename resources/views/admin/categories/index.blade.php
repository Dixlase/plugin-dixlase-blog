{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Categories index with drag-and-drop reordering (SortableJS via the
blogCategoryReorder Alpine component) plus inline forms: create at the
top level, add a child under a row, edit a row.

The outer x-data (blogCategoryReorder) holds, in a single Alpine scope:
  - Sortable instances + persist endpoint (data-save-url / data-csrf-token)
  - which inline form is open; data-reopen names the form a failed
    submit came from, so it opens again with its errors
  - data-max-depth, the deepest level drag and drop may produce
The single scope keeps the toolbar (saving / saved indicators next to
the New Category button) coherent without prop-drilling.
--}}

@extends('layouts.admin')

@section('content')
    <div x-data="blogCategoryReorder"
         class="dixlase-blog-categories"
         data-save-url="{{ route('dixlase-blog::admin.blog.categories.reorder') }}"
         data-csrf-token="{{ csrf_token() }}"
         data-max-depth="{{ \Plugins\DixlaseBlog\App\Models\BlogCategory::MAX_DEPTH }}"
         data-reopen="{{ old('_form') }}">

        <div class="mb-4 flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('dixlase-blog::admin.category.heading') }}
            </h1>

            <div class="flex items-center gap-3">
                {{-- Save status indicator, read out after a move --}}
                <span role="status" aria-live="polite" class="inline-flex items-center gap-3">
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
                </span>

                <x-form-button
                    type="button"
                    icon="fas fa-plus"
                    size="sm"
                    variant="primary"
                    @click="toggleCreate()"
                >
                    {{ __('dixlase-blog::admin.category.new_button') }}
                </x-form-button>
            </div>
        </div>

        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('dixlase-blog::admin.category.reorder_help') }}
        </p>

        {{-- Inline create form (top level) --}}
        <div x-show="isOpen('create')" x-cloak
             class="mb-4 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 p-4">
            @include('dixlase-blog::admin.categories._create-form', [
                'formKey' => 'create',
                'isCurrent' => old('_form') === 'create',
                'parent' => null,
            ])
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
                <ul class="category-tree list-none min-h-4 space-y-0" data-parent-id="" data-tree-root>
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

@push('styles')
<style @cspNonce>
/* Drag and drop: show where a row will land. Empty child lists that may
   take the dragged row open up into a visible drop zone. */
.dixlase-blog-categories .category-tree.is-drop-target {
    outline: 2px dashed var(--color-blue-500);
    outline-offset: 2px;
    border-radius: 0.375rem;
    background-color: color-mix(in srgb, var(--color-blue-500) 6%, transparent);
}
.dixlase-blog-categories.is-dragging .category-tree.can-accept:not(:has(> li)) {
    min-height: 2.25rem;
    margin: 0.25rem 0.5rem 0.5rem 1.5rem;
    border: 1px dashed var(--color-gray-400);
    border-radius: 0.375rem;
}
.dixlase-blog-categories .dixlase-blog-category-ghost {
    opacity: 0.5;
    outline: 2px solid var(--color-blue-500);
}
</style>
@endpush
