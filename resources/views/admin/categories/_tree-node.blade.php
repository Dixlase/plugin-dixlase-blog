{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Recursive tree-node partial used by categories/index.blade.php.
Each <li> carries data-category-id so blog-category-reorder.js can read
it back when serializing the dropped tree. The <ul.category-tree> child
container is always rendered (even empty) so SortableJS has a drop
target for "make this category a child of X"; the component refuses
drops that would go deeper than BlogCategory::MAX_DEPTH.
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

        <div class="flex items-center justify-end gap-2 shrink-0">
            {{-- "+" and "Move up a level" follow the row's depth; the JS
                 component updates them after a drag, see refreshRows(). --}}
            <x-form-button
                type="button"
                variant="ghost"
                size="sm"
                icon="fas fa-plus"
                data-when="can-add-child"
                :hidden="! $category->canHaveChildren()"
                :xClick="'toggleChild('.$category->id.')'"
                :title="__('dixlase-blog::admin.category.add_child')"
                :aria-label="__('dixlase-blog::admin.category.add_child')"
            />

            <form id="outdent-cat-{{ $category->id }}"
                  method="POST"
                  action="{{ route('dixlase-blog::admin.blog.categories.outdent', ['category' => $category->id]) }}">
                @csrf
            </form>
            <x-form-button
                type="submit"
                variant="ghost"
                size="sm"
                icon="fas fa-outdent"
                form="outdent-cat-{{ $category->id }}"
                data-when="can-outdent"
                :hidden="$category->parent_id === null"
                :title="__('dixlase-blog::admin.category.move_up')"
                :aria-label="__('dixlase-blog::admin.category.move_up')"
            />

            <x-form-button
                type="button"
                variant="ghost"
                size="sm"
                icon="fas fa-edit"
                :xClick="'toggleEdit('.$category->id.')'"
                :title="__('dixlase-blog::admin.category.edit')"
                :aria-label="__('dixlase-blog::admin.category.edit')"
            />

            <form id="delete-cat-{{ $category->id }}"
                  method="POST"
                  action="{{ route('dixlase-blog::admin.blog.categories.destroy', ['category' => $category->id]) }}">
                @csrf
                @method('DELETE')
            </form>
            <x-form-button
                type="button"
                variant="ghost"
                size="sm"
                icon="fas fa-trash"
                class="!text-red-600 hover:!text-red-900 dark:!text-red-400 dark:hover:!text-red-300"
                :xClick="'openModal(\'delete-cat-modal-'.$category->id.'\')'"
                :title="__('dixlase-blog::admin.category.delete')"
                :aria-label="__('dixlase-blog::admin.category.delete')"
            />
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

    {{-- Inline form: a child under this row --}}
    <div x-show="isOpen('child-{{ $category->id }}')" x-cloak
         class="border-t border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20 px-3 py-3">
        @include('dixlase-blog::admin.categories._create-form', [
            'formKey' => 'child-'.$category->id,
            'isCurrent' => old('_form') === 'child-'.$category->id,
            'parent' => $category,
        ])
    </div>

    {{-- Inline edit form. The parent is changed by drag and drop or by
         "Move up a level", not here. --}}
    <div x-show="isOpen('edit-{{ $category->id }}')" x-cloak
         class="border-t border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/20 px-3 py-3">
        <form action="{{ route('dixlase-blog::admin.blog.categories.update', ['category' => $category->id]) }}"
              method="POST"
              class="space-y-3">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit-{{ $category->id }}">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-form-label :for="'edit-label-'.$category->id" :text="__('dixlase-blog::admin.category.label_label')" />
                    <x-form-text :id="'edit-label-'.$category->id" name="label" :value="old('_form') === 'edit-'.$category->id ? old('label') : $category->label" required />
                    @if (old('_form') === 'edit-'.$category->id)
                        <x-form-error name="label" />
                    @endif
                </div>
                <div>
                    <x-form-label :for="'edit-slug-'.$category->id" :text="__('dixlase-blog::admin.category.slug_label')" />
                    <x-form-text :id="'edit-slug-'.$category->id" name="slug" :value="old('_form') === 'edit-'.$category->id ? old('slug') : $category->slug" class="font-mono" />
                    @if (old('_form') === 'edit-'.$category->id)
                        <x-form-error name="slug" />
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                <x-form-button type="submit" icon="fas fa-save" size="sm" variant="primary">
                    {{ __('dixlase-blog::admin.category.save') }}
                </x-form-button>
                <x-form-button type="button" size="sm" variant="secondary" @click="closeForms()">
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
