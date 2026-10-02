@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0 space-y-8">
        <header class="mb-2">
            <div class="sr-only">
                <h1>{{ __('admin_pages.ai_citation_sources') }}</h1>
                <p>{{ __('admin.distribution.ai_sources.subtitle') }}</p>
            </div>
            <x-admin.v3.distribution-subnav active="ai-sources" />
        </header>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ __('admin.distribution.ai_sources.stats_total') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ __('admin.distribution.ai_sources.stats_enabled') }}</p>
                <p class="mt-1 text-2xl font-bold text-blue-600">{{ $stats['enabled'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ __('admin.distribution.ai_sources.stats_publishable') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['publishable'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ __('admin.distribution.ai_sources.stats_covered') }}</p>
                <p class="mt-1 text-2xl font-bold {{ $stats['covered'] > 0 ? 'text-green-600' : 'text-amber-600' }}">{{ $stats['covered'] }}/{{ $stats['publishable'] }}</p>
            </div>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">{{ __('admin.distribution.ai_sources.tools_title') }}</h2>
            <p class="mt-1 text-sm leading-6 text-gray-500">{{ __('admin.distribution.ai_sources.tools_desc') }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($aiTools as $toolKey => $toolName)
                    <span class="inline-flex h-8 items-center rounded-full bg-blue-50 px-3 text-sm font-medium text-blue-700">{{ $toolName }}</span>
                @endforeach
            </div>
        </section>

        <form method="POST" action="{{ route('admin.distribution.ai-sources.update') }}">
            @csrf
            @method('PUT')

            @foreach ($groups as $category => $sources)
                <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">{{ $categoryLabels[$category] ?? $category }}</h2>

                    <div class="mt-4 divide-y divide-gray-100">
                        @foreach ($sources as $source)
                            <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-start gap-3">
                                    <input type="checkbox" name="enabled_sources[]" value="{{ $source['key'] }}" @checked($source['enabled'])
                                           class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           aria-label="{{ $source['name'] }}">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $source['name'] }}
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500">{{ $source['domain'] }}</span>
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500">{{ __('admin.distribution.cn.tier_' . $source['tier']) }}</span>
                                        </p>
                                        <p class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($source['cited_by'] as $toolKey)
                                                <span class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{{ $aiTools[$toolKey] ?? $toolKey }}</span>
                                            @endforeach
                                        </p>
                                        @if ($source['note'] !== '')
                                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $source['note'] }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="shrink-0 text-sm">
                                    @if ($source['publishable'] && $source['channels'] !== [])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                            <i data-lucide="circle-check" class="h-3.5 w-3.5"></i>
                                            {{ __('admin.distribution.ai_sources.covered', ['count' => count($source['channels'])]) }}
                                        </span>
                                        <div class="mt-2 flex flex-col items-end gap-1">
                                            @foreach ($source['channels'] as $channel)
                                                <a href="{{ route('admin.distribution.show', ['channelId' => $channel['id']]) }}" class="text-xs text-blue-600 hover:underline">
                                                    {{ $channel['name'] }}
                                                    @if ($channel['auth_mode'] !== '')
                                                        <span class="text-gray-400">· {{ __('admin.distribution.cn.mode_' . str_replace('-', '_', $channel['auth_mode'])) }}</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @elseif ($source['publishable'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                                            <i data-lucide="circle-alert" class="h-3.5 w-3.5"></i>
                                            {{ __('admin.distribution.ai_sources.uncovered') }}
                                        </span>
                                        <a href="{{ route('admin.distribution.create', ['channel_type' => 'cn_platform', 'cn_platform' => $source['cn_platform_key']]) }}"
                                           class="mt-2 inline-flex min-h-10 items-center rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-100">
                                            <i data-lucide="plus" class="mr-1 h-3.5 w-3.5"></i>
                                            {{ __('admin.distribution.ai_sources.create_channel') }}
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">
                                            <i data-lucide="link" class="h-3.5 w-3.5"></i>
                                            {{ __('admin.distribution.ai_sources.indirect') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="flex items-center justify-end gap-3">
                <button type="submit" class="inline-flex min-h-10 items-center rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    <i data-lucide="save" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.distribution.ai_sources.save') }}
                </button>
            </div>
        </form>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">{{ __('admin.distribution.ai_sources.guide_title') }}</h2>
            <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600">
                <li class="flex gap-3"><i data-lucide="sparkles" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.distribution.ai_sources.guide_item_1') }}</li>
                <li class="flex gap-3"><i data-lucide="send" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.distribution.ai_sources.guide_item_2') }}</li>
                <li class="flex gap-3"><i data-lucide="eye" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.distribution.ai_sources.guide_item_3') }}</li>
            </ul>
        </section>
    </div>
@endsection
