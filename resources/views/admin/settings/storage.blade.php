{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.
--}}

@extends('dixlase-blog::admin.settings._screen')

{{-- Where new posts are stored, and how a change made to the files of a
     post stored as files is published (#193). File storage is not offered
     in Simple mode, so neither is this screen's choice; the values are
     kept. --}}
@section('fields')
    @if ($isSimpleMode)
        <x-ui-message type="info" :message="__('dixlase-blog::admin.settings.storage_simple_mode')" />
    @else
        <section class="mb-8">
            <x-form-label :text="__('dixlase-blog::admin.settings.default_storage_type_label')" class="mb-2" />
            <x-form-radio-card-group
                name="default_storage_type"
                :options="$storageTypeOptions"
                :value="old('default_storage_type', $blogSettings['default_storage_type'])"
                :columns="2"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.settings.default_storage_type_help')" />
            <x-form-error name="default_storage_type" />
        </section>

        <section>
            <x-form-label :text="__('common.content_publish_mode.label')" class="mb-2" />
            <x-form-radio-card-group
                name="publish_mode"
                :options="$publishModeOptions"
                :value="old('publish_mode', $publishMode)"
                :columns="2"
            />
            <x-form-help-text :text="__('dixlase-blog::admin.settings.publish_mode_help')" />
            <x-form-error name="publish_mode" />
            {{-- Which mode takes effect here: core applies the file save
                 mode only in a local environment. --}}
            <div class="mt-4">
                @if ($fileSaveModeAvailable)
                    <x-ui-message type="info" :message="__('common.content_publish_mode.in_effect_local')" />
                @else
                    <x-ui-message type="warning" :message="__('common.content_publish_mode.not_in_effect')" />
                @endif
            </div>
            <x-content.scheduler-notice :running="$schedulerRunning" />
        </section>
    @endif
@endsection
