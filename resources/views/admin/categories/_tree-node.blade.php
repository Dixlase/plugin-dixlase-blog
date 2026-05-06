{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Recursive tree-node partial used by categories/index.blade.php.
Each <li> carries data-category-id so blog-category-reorder.js can read
it back when serializing the dropped tree. The <ul.category-tree> child
container is always rendered (even empty) so SortableJS has a drop
target for "make this category a child of X".
--}}

<li data-category-id="{{ $category->id }}"
    class="bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 mb-1">
    <div class="flex items-center gap-2 px-2 py-2">
        <button type="button"
                class="drag-handle cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 px-1"
                :title="'{{ __('dixlase-blog::admin.category.drag_handle_label') }}'"
                aria-label="{{ __('dixlase-blog::admin.category.drag_handle_label') }}">
            <i class="fas fa-grip-vertical"></i>
        </button>

        <div class="flex-1 flex items-center gap-2 min-w-0">
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $category->label }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate">{{ $category->slug }}</span>
            @if (($category->posts_count ?? 0) > 0)
                <span class="inline-flex items-center text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                    {{ $category->posts_count }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-1 shrink-0">
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
        </div>
    </div>

    {{-- Inline edit form --}}
    <div x-show="openId === {{ $category->id }}" x-cloak
         class="border-t border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20 px-3 py-3">
        <form action="{{ route('dixlase-blog::admin.blog.categories.update', ['category' => $category->id]) }}"
              method="POST"
              class="space-y-3">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <x-form-label :for="'edit-label-'.$category->id" :text="__('dixlase-blog::admin.category.label_label')" />
                    <x-form-text :id="'edit-label-'.$category->id" name="label" :value="old('label', $category->label)" required />
                    <x-form-error name="label" />
                </div>
                <div>
                    <x-form-label :for="'edit-slug-'.$category->id" :text="__('dixlase-blog::admin.category.slug_label')" />
                    <x-form-text :id="'edit-slug-'.$category->id" name="slug" :value="old('slug', $category->slug)" class="font-mono" />
                    <x-form-error name="slug" />
                </div>
                <div>
                    <x-form-label :for="'edit-parent-'.$category->id" :text="__('dixlase-blog::admin.category.parent_label')" />
                    <select :id="'edit-parent-'.$category->id" name="parent_id" class="input-common input-full">
                        <option value="">{{ __('dixlase-blog::admin.category.parent_none') }}</option>
                        @foreach ($allCategories as $cat)
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
    </div>

    {{-- Children (always rendered as a drop target, even when empty) --}}
    <ul class="category-tree list-none pl-6 pr-2 py-1 min-h-2 space-y-0"
        data-parent-id="{{ $category->id }}">
        @foreach ($allCategories->where('parent_id', $category->id) as $child)
            @include('dixlase-blog::admin.categories._tree-node', [
                'category' => $child,
                'allCategories' => $allCategories,
            ])
        @endforeach
    </ul>
</li>
