{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Sidebar settings: whether it shows, where, its widgets and their order.
--}}

@extends('dixlase-blog::admin.settings._screen')

@section('fields')
    <div class="grid grid-cols-1 gap-6">
        <div>
            <x-form-toggle
                id="sidebar_enabled"
                name="sidebar_enabled"
                :label="__('dixlase-blog::admin.settings.sidebar_enabled_label')"
                :checked="(bool) old('sidebar_enabled', $blogSettings['sidebar_enabled'])"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.settings.sidebar_enabled_help')" />
            <x-form-error name="sidebar_enabled" />
        </div>

        <div>
            <x-form-label :text="__('dixlase-blog::admin.settings.sidebar_position_label')" :required="true" class="mb-2" />
            <x-form-radio-card-group
                name="sidebar_position"
                :options="$sidebarPositionOptions"
                :value="old('sidebar_position', $blogSettings['sidebar_position'])"
                :columns="2"
            />
            <x-form-error name="sidebar_position" />
        </div>

        <div>
            <x-form-label :text="__('dixlase-blog::admin.settings.sidebar_widgets_label')" class="mb-2" />
            <div x-data="blogSidebarWidgets">
                <ul x-ref="list" class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    @foreach ($sidebarWidgets as $widget)
                        <li class="flex items-center gap-3 bg-white px-3 py-2 dark:bg-gray-800">
                            <span data-drag-handle class="cursor-move text-gray-400" title="{{ __('dixlase-blog::admin.settings.sidebar_widget_drag') }}">
                                <i class="fas fa-grip-vertical" aria-hidden="true"></i>
                            </span>
                            <input type="hidden" name="sidebar_widgets[]" value="{{ $widget['key'] }}">
                            <x-form-toggle
                                :id="'sidebar_widget_'.$widget['key']"
                                :name="'sidebar_widget_enabled['.$widget['key'].']'"
                                :label="$widget['label']"
                                :checked="(bool) old('sidebar_widget_enabled.'.$widget['key'], $widget['enabled'])"
                            />
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-form-help-text :text="__('dixlase-blog::admin.settings.sidebar_widgets_help')" />
            <x-form-error name="sidebar_widgets" />
        </div>

        <div class="max-w-xs">
            <x-form-label
                for="sidebar_recent_count"
                :text="__('dixlase-blog::admin.settings.sidebar_recent_count_label')"
                :required="true"
            />
            <x-form-text
                id="sidebar_recent_count"
                name="sidebar_recent_count"
                type="number"
                :min="$recentPostsMin"
                :max="$recentPostsMax"
                :value="old('sidebar_recent_count', $blogSettings['sidebar_recent_count'])"
                :required="true"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.settings.sidebar_recent_count_help', ['min' => $recentPostsMin, 'max' => $recentPostsMax])" />
            <x-form-error name="sidebar_recent_count" />
        </div>
    </div>
@endsection
