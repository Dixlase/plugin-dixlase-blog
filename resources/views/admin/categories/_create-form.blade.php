{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Create form for a category. The top "New category" form makes a
top-level one; a row's "+" opens it with that row as the parent.
_form names the form, so a failed submit opens the same form again.

@param string $formKey  "create" or "child-{id}"
@param bool $isCurrent  whether the last failed submit came from this form
@param \Plugins\DixlaseBlog\App\Models\BlogCategory|null $parent
--}}

<form action="{{ route('dixlase-blog::admin.blog.categories.store') }}" method="POST" class="space-y-3">
    @csrf
    <input type="hidden" name="_form" value="{{ $formKey }}">
    @if ($parent)
        <input type="hidden" name="parent_id" value="{{ $parent->id }}">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ __('dixlase-blog::admin.category.add_child_heading', ['parent' => $parent->label]) }}
        </p>
    @endif
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
            <x-form-label :for="$formKey.'-label'" :text="__('dixlase-blog::admin.category.label_label')" />
            <x-form-text :id="$formKey.'-label'" name="label" :value="$isCurrent ? old('label') : ''" required />
            @if ($isCurrent)
                <x-form-error name="label" />
                <x-form-error name="parent_id" />
            @endif
        </div>
        <div>
            <x-form-label :for="$formKey.'-slug'" :text="__('dixlase-blog::admin.category.slug_label')" />
            <x-form-text :id="$formKey.'-slug'" name="slug" :value="$isCurrent ? old('slug') : ''" class="font-mono" />
            <x-form-help-text :text="__('dixlase-blog::admin.category.slug_help')" />
            @if ($isCurrent)
                <x-form-error name="slug" />
            @endif
        </div>
        {{-- Source language of the name; fixed once saved. Other
             languages are added in the translation editor. --}}
        @if ($multilingualEnabled)
            <div>
                <x-form-label :for="$formKey.'-lang'" :text="__('dixlase-blog::admin.category.lang_label')" />
                <x-form-select :id="$formKey.'-lang'" name="lang" :options="$languageOptions" :value="$langValue" />
                <x-form-help-text :text="__('dixlase-blog::admin.category.lang_help')" />
                @if ($isCurrent)
                    <x-form-error name="lang" />
                @endif
            </div>
        @endif
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
