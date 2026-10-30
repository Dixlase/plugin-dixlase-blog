{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

The Tags screen, modelled on the media library's: every tag with its
slug, language and number of posts, a form to add one, and per row a
modal to rename it and one to delete it. The modals are core's
<x-ui-modal>, opened with openModal(); each submits its row's form.
--}}

@extends('layouts.admin')

@section('content')
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('dixlase-blog::admin.tags.intro') }}</p>

    <section class="mb-6">
        <form method="POST" action="{{ route('dixlase-blog::admin.blog.tags.store') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            {{-- Edits share the field names; only refill after an add. --}}
            <input type="hidden" name="_form" value="create">
            <div class="flex-1">
                <x-form-label for="new-tag-name" :text="__('dixlase-blog::admin.tags.create_name')" :required="true" />
                <x-form-text
                    id="new-tag-name"
                    name="name"
                    :value="old('_form') === 'create' ? old('name') : ''"
                    :maxlength="\Plugins\DixlaseBlog\App\Models\BlogTag::NAME_MAX"
                    :placeholder="__('dixlase-blog::admin.tags.create_placeholder')"
                    :required="true"
                />
            </div>
            <div class="sm:w-64">
                <x-form-label for="new-tag-slug" :text="__('dixlase-blog::admin.tags.create_slug')" />
                <x-form-text
                    id="new-tag-slug"
                    name="slug"
                    :value="old('_form') === 'create' ? old('slug') : ''"
                    :maxlength="\Plugins\DixlaseBlog\App\Models\BlogTag::NAME_MAX"
                    class="font-mono"
                />
            </div>
            <x-form-button type="submit" variant="primary" icon="fas fa-plus" :label="__('dixlase-blog::admin.tags.create_submit')" />
        </form>
    </section>

    @if ($tags->isEmpty())
        <div class="empty-state">
            <i class="fas fa-tags text-6xl" aria-hidden="true"></i>
            <p>{{ __('dixlase-blog::admin.tags.empty') }}</p>
        </div>
    @else
        <div class="responsive-table">
            <table class="admin-table">
                <caption class="sr-only">{{ __('dixlase-blog::admin.blog.tags.index.heading') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('dixlase-blog::admin.tags.name') }}</th>
                        <th scope="col">{{ __('dixlase-blog::admin.tags.slug') }}</th>
                        <th scope="col">{{ __('dixlase-blog::admin.tags.lang') }}</th>
                        <th scope="col">{{ __('dixlase-blog::admin.tags.posts') }}</th>
                        <th scope="col">{{ __('dixlase-blog::admin.tags.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tags as $tag)
                        <tr>
                            <td data-label="{{ __('dixlase-blog::admin.tags.name') }}">
                                <a href="{{ route('dixlase-blog::front.tag', ['slug' => $tag->slug]) }}"
                                   target="_blank" rel="noopener"
                                   title="{{ __('dixlase-blog::admin.tags.view', ['name' => $tag->name]) }}"
                                   class="break-all hover:underline">{{ $tag->name }}</a>
                            </td>
                            <td data-label="{{ __('dixlase-blog::admin.tags.slug') }}" class="font-mono break-all">{{ $tag->slug }}</td>
                            <td data-label="{{ __('dixlase-blog::admin.tags.lang') }}">{{ $tag->lang }}</td>
                            <td data-label="{{ __('dixlase-blog::admin.tags.posts') }}">{{ trans_choice('dixlase-blog::admin.tags.posts_count', $tag->posts_count, ['count' => $tag->posts_count]) }}</td>
                            <td data-label="{{ __('dixlase-blog::admin.tags.actions') }}">
                                <div class="flex items-center justify-end gap-1">
                                    <x-form-button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        icon="fas fa-pen"
                                        :xClick="'openModal(\'edit-tag-modal-'.$tag->id.'\')'"
                                        :title="__('dixlase-blog::admin.tags.edit_action', ['name' => $tag->name])"
                                        :aria-label="__('dixlase-blog::admin.tags.edit_action', ['name' => $tag->name])"
                                    />
                                    <x-form-button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        icon="fas fa-trash"
                                        class="!text-red-600 hover:!text-red-900 dark:!text-red-400 dark:hover:!text-red-300"
                                        :xClick="'openModal(\'delete-tag-modal-'.$tag->id.'\')'"
                                        :title="__('dixlase-blog::admin.tags.delete_action', ['name' => $tag->name])"
                                        :aria-label="__('dixlase-blog::admin.tags.delete_action', ['name' => $tag->name])"
                                    />
                                </div>

                                <form id="edit-tag-{{ $tag->id }}" method="POST" action="{{ route('dixlase-blog::admin.blog.tags.update', ['tag' => $tag->id]) }}" class="hidden">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="_form" value="edit-{{ $tag->id }}">
                                </form>
                                <x-ui-modal
                                    :id="'edit-tag-modal-'.$tag->id"
                                    :title="__('dixlase-blog::admin.tags.edit_title')"
                                    message=""
                                    :confirm_label="__('dixlase-blog::admin.tags.edit_confirm')"
                                    :cancel_label="__('common.cancel')"
                                    icon_type="info"
                                    :form="'edit-tag-'.$tag->id"
                                >
                                    <div class="space-y-3 text-left">
                                        <div>
                                            <x-form-label :for="'edit-tag-name-'.$tag->id" :text="__('dixlase-blog::admin.tags.name')" :required="true" />
                                            <x-form-text
                                                :id="'edit-tag-name-'.$tag->id"
                                                name="name"
                                                :form="'edit-tag-'.$tag->id"
                                                :value="old('_form') === 'edit-'.$tag->id ? old('name') : $tag->name"
                                                :maxlength="\Plugins\DixlaseBlog\App\Models\BlogTag::NAME_MAX"
                                                :required="true"
                                            />
                                        </div>
                                        <div>
                                            <x-form-label :for="'edit-tag-slug-'.$tag->id" :text="__('dixlase-blog::admin.tags.slug')" />
                                            <x-form-text
                                                :id="'edit-tag-slug-'.$tag->id"
                                                name="slug"
                                                :form="'edit-tag-'.$tag->id"
                                                :value="old('_form') === 'edit-'.$tag->id ? old('slug') : $tag->slug"
                                                :maxlength="\Plugins\DixlaseBlog\App\Models\BlogTag::NAME_MAX"
                                                class="font-mono"
                                            />
                                            <x-form-help-text :text="__('dixlase-blog::admin.tags.edit_slug_help')" />
                                        </div>
                                    </div>
                                </x-ui-modal>

                                <form id="delete-tag-{{ $tag->id }}" method="POST" action="{{ route('dixlase-blog::admin.blog.tags.destroy', ['tag' => $tag->id]) }}" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <x-ui-modal
                                    :id="'delete-tag-modal-'.$tag->id"
                                    :title="__('dixlase-blog::admin.tags.delete_title')"
                                    :message="__('dixlase-blog::admin.tags.delete_message', ['name' => e($tag->name), 'count' => $tag->posts_count])"
                                    :confirm_label="__('common.delete')"
                                    :cancel_label="__('common.cancel')"
                                    icon_type="danger"
                                    confirm_color="red"
                                    :form="'delete-tag-'.$tag->id"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
