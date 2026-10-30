{{--
This file is part of Dixlase Blog.

Copyright (C) 2026 exc-D inc.
https://exc-d.com

Dixlase Blog is dual-licensed. Same terms as edit.blade.php.

Share button settings: one switch per service.
--}}

@extends('dixlase-blog::admin.settings._screen')

@section('fields')
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('dixlase-blog::admin.settings.share_help') }}</p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach ($shareServices as $service)
            <x-form-toggle
                :id="'share_service_'.$service['key']"
                :name="'share_services['.$service['key'].']'"
                :label="$service['label']"
                :checked="(bool) old('share_services.'.$service['key'], $service['enabled'])"
            />
        @endforeach
    </div>
    <x-form-error name="share_services" />
@endsection
