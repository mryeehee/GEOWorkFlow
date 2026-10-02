@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0 space-y-8">
        <header class="mb-2">
            <div class="sr-only">
                <h1>{{ __('admin_pages.content_directions') }}</h1>
                <p>{{ __('admin.content_directions.subtitle') }}</p>
            </div>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1">
                    <h2 class="sr-only">{{ __('admin_pages.content_directions') }}</h2>
                    <p class="text-sm leading-6 text-gray-500">{{ __('admin.content_directions.subtitle') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.analytics.content-directions.generate') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="inline-flex min-h-10 items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        <i data-lucide="sparkles" class="mr-2 h-4 w-4"></i>
                        {{ __('admin.content_directions.generate') }}
                    </button>
                </form>
            </div>
        </header>

        @if ($latest !== null && in_array((string) $latest->status, [\App\Models\ContentDirection::STATUS_QUEUED, \App\Models\ContentDirection::STATUS_GENERATING], true))
            <div class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                <i data-lucide="loader" class="h-4 w-4 animate-spin"></i>
                {{ __('admin.content_directions.generating') }}
            </div>
        @endif

        @if ($latest !== null && (string) $latest->status === \App\Models\ContentDirection::STATUS_FAILED)
            <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <i data-lucide="circle-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
                <div>
                    <p class="font-semibold">{{ __('admin.content_directions.failed') }}</p>
                    @if ($latest->error_message)
                        <p class="mt-1 text-xs leading-5">{{ $latest->error_message }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if ($latest !== null && (array) $latest->suggestions_json !== [])
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.content_directions.latest_title') }}</h2>
                    <p class="text-xs text-gray-500">
                        {{ $latest->generated_at ? $latest->generated_at->format('Y-m-d H:i') : $latest->created_at?->format('Y-m-d H:i') }}
                    </p>
                </div>

                <ol class="mt-4 space-y-3">
                    @foreach ((array) $latest->suggestions_json as $index => $suggestion)
                        <li class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">
                                        <span class="mr-2 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-xs font-bold text-white">{{ (int) ($suggestion['priority'] ?? $index + 1) }}</span>
                                        {{ $suggestion['topic'] ?? '' }}
                                    </p>
                                    <p class="mt-2 flex flex-wrap gap-1 text-xs">
                                        @if (($suggestion['keyword'] ?? '') !== '')
                                            <span class="rounded bg-blue-50 px-2 py-0.5 text-blue-700">{{ $suggestion['keyword'] }}</span>
                                        @endif
                                        @if (($suggestion['angle'] ?? '') !== '')
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-gray-600">{{ $suggestion['angle'] }}</span>
                                        @endif
                                        @foreach ((array) ($suggestion['target_sources'] ?? []) as $targetSource)
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-gray-600">{{ $targetSource }}</span>
                                        @endforeach
                                    </p>
                                    @if (($suggestion['reason'] ?? '') !== '')
                                        <p class="mt-2 text-xs leading-5 text-gray-500">{{ $suggestion['reason'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>

                @php $inputs = (array) $latest->inputs_json; @endphp
                @if (($inputs['visibility_gaps'] ?? []) !== [] || ($inputs['source_gaps'] ?? []) !== [])
                    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @if (($inputs['visibility_gaps'] ?? []) !== [])
                            <div class="rounded-lg border border-gray-100 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.content_directions.visibility_gaps') }}</h3>
                                <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                    @foreach ((array) $inputs['visibility_gaps'] as $gap)
                                        <li class="flex items-center justify-between gap-2">
                                            <span>{{ $gap['keyword'] ?? '' }}</span>
                                            <span class="text-gray-400">{{ (int) ($gap['owned_citations'] ?? 0) }}/{{ (int) ($gap['total_citations'] ?? 0) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (($inputs['source_gaps'] ?? []) !== [])
                            <div class="rounded-lg border border-gray-100 p-4">
                                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.content_directions.source_gaps') }}</h3>
                                <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                    @foreach ((array) $inputs['source_gaps'] as $gap)
                                        <li>{{ $gap['name'] ?? '' }} · {{ $gap['domain'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        @else
            @if ($latest === null || (string) $latest->status === \App\Models\ContentDirection::STATUS_QUEUED || (string) $latest->status === \App\Models\ContentDirection::STATUS_GENERATING)
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="py-10 text-center">
                        <i data-lucide="sparkles" class="mx-auto h-12 w-12 text-gray-300"></i>
                        <h2 class="mt-4 text-base font-semibold text-gray-900">{{ __('admin.content_directions.empty_title') }}</h2>
                        <p class="mx-auto mt-1 max-w-xl text-sm leading-6 text-gray-500">{{ __('admin.content_directions.empty_desc') }}</p>
                    </div>
                </section>
            @endif
        @endif

        @if ($history->count() > 1)
            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.content_directions.history_title') }}</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($history as $row)
                        <div class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                            <div class="flex items-center gap-3">
                                @if ((string) $row->status === \App\Models\ContentDirection::STATUS_COMPLETED)
                                    <span class="inline-flex h-6 items-center rounded-full bg-green-50 px-2 text-xs font-medium text-green-700">{{ __('admin.content_directions.status_completed') }}</span>
                                @elseif ((string) $row->status === \App\Models\ContentDirection::STATUS_FAILED)
                                    <span class="inline-flex h-6 items-center rounded-full bg-red-50 px-2 text-xs font-medium text-red-700">{{ __('admin.content_directions.status_failed') }}</span>
                                @else
                                    <span class="inline-flex h-6 items-center rounded-full bg-blue-50 px-2 text-xs font-medium text-blue-700">{{ __('admin.content_directions.status_generating') }}</span>
                                @endif
                                <span class="text-xs text-gray-500">{{ count((array) $row->suggestions_json) }} {{ __('admin.content_directions.items_unit') }}</span>
                            </div>
                            <span class="text-xs text-gray-400">{{ ($row->generated_at ?? $row->created_at)?->format('Y-m-d H:i') }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">{{ __('admin.content_directions.guide_title') }}</h2>
            <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600">
                <li class="flex gap-3"><i data-lucide="layers" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.content_directions.guide_item_1') }}</li>
                <li class="flex gap-3"><i data-lucide="trending-up" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.content_directions.guide_item_2') }}</li>
                <li class="flex gap-3"><i data-lucide="refresh-cw" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.content_directions.guide_item_3') }}</li>
            </ul>
        </section>
    </div>
@endsection
