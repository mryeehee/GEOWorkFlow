@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="mb-8 flex items-center space-x-4">
            <a href="{{ route('admin.distribution.show', ['channelId' => (int) $channel->id]) }}" aria-label="{{ __('admin.common.back') }}" class="text-gray-400 hover:text-gray-600">
                <i data-lucide="arrow-left" class="h-5 w-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.distribution.oauth.title') }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $channel->name }}</p>
            </div>
        </div>

        <div class="rounded-lg bg-white shadow">
            <div class="px-6 py-6 space-y-6">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.distribution.oauth.invalid_title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600">{{ $oauthError ?? '' }}</p>
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.distribution.edit', ['channelId' => (int) $channel->id]) }}" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        {{ __('admin.distribution.oauth.go_edit') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
