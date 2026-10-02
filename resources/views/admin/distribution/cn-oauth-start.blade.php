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
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.distribution.oauth.redirect_uri_title') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.distribution.oauth.redirect_uri_help') }}</p>
                    <code class="mt-3 block break-all rounded-md bg-white px-3 py-2 text-sm text-gray-800 ring-1 ring-gray-200">{{ $redirectUri }}</code>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.distribution.oauth.steps_title') }}</h2>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-600">
                        <li>{{ __('admin.distribution.oauth.step_1') }}</li>
                        <li>{{ __('admin.distribution.oauth.step_2') }}</li>
                        <li>{{ __('admin.distribution.oauth.step_3') }}</li>
                    </ol>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.distribution.show', ['channelId' => (int) $channel->id]) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('admin.button.cancel') }}</a>
                    <a href="{{ $authorizeUrl }}" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <i data-lucide="qr-code" class="mr-2 h-4 w-4"></i>
                        {{ __('admin.distribution.oauth.start_button') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
