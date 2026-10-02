@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0 space-y-8">
        <header class="mb-2">
            <div class="sr-only">
                <h1>{{ __('admin_pages.keyword_opportunities') }}</h1>
                <p>{{ __('admin.keyword_opportunities.subtitle') }}</p>
            </div>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <x-admin.v3.materials-subnav active="keyword-opportunities" />
                </div>
                <form method="POST" action="{{ route('admin.keyword-opportunities.mine') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="inline-flex min-h-10 items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        <i data-lucide="radar" class="mr-2 h-4 w-4"></i>
                        {{ __('admin.keyword_opportunities.mine') }}
                    </button>
                </form>
            </div>
            <p class="mt-2 text-sm leading-6 text-gray-500">{{ __('admin.keyword_opportunities.mine_desc') }}</p>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ __('admin.keyword_opportunities.stats_total') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ (int) $stats['total'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ __('admin.keyword_opportunities.stats_new') }}</p>
                <p class="mt-1 text-2xl font-bold text-blue-600">{{ (int) $stats['new'] }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ __('admin.keyword_opportunities.stats_imported') }}</p>
                <p class="mt-1 text-2xl font-bold text-green-600">{{ (int) $stats['imported'] }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach (['' => 'filter_all', 'new' => 'status_new', 'imported' => 'status_imported', 'dismissed' => 'status_dismissed'] as $filterStatus => $labelKey)
                <a href="{{ route('admin.keyword-opportunities.index', $filterStatus !== '' ? ['status' => $filterStatus] : []) }}"
                   @class([
                       'inline-flex h-9 items-center rounded-full px-3 text-sm font-medium transition-colors',
                       'bg-blue-600 text-white' => $status === (string) $filterStatus,
                       'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' => $status !== (string) $filterStatus,
                   ])>
                    {{ __('admin.keyword_opportunities.' . $labelKey) }}
                </a>
            @endforeach
        </div>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
            @if ($opportunities->count() === 0)
                <div class="px-6 py-12 text-center">
                    <i data-lucide="radar" class="mx-auto h-12 w-12 text-gray-300"></i>
                    <h2 class="mt-4 text-base font-semibold text-gray-900">{{ __('admin.keyword_opportunities.empty_title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">{{ __('admin.keyword_opportunities.empty_desc') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($opportunities as $opportunity)
                        @php
                            $analysis = (array) $opportunity->analysis_json;
                            $evidence = (array) $opportunity->evidence_json;
                        @endphp
                        <div class="flex flex-col gap-3 px-6 py-5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $opportunity->keyword }}</h3>
                                    <span class="inline-flex h-6 items-center rounded-full bg-blue-50 px-2 text-xs font-bold text-blue-700">{{ (int) $opportunity->score }}</span>
                                    @if ($opportunity->status === \App\Models\KeywordOpportunity::STATUS_IMPORTED)
                                        <span class="inline-flex h-6 items-center rounded-full bg-green-50 px-2 text-xs font-medium text-green-700">{{ __('admin.keyword_opportunities.status_imported') }}</span>
                                    @elseif ($opportunity->status === \App\Models\KeywordOpportunity::STATUS_DISMISSED)
                                        <span class="inline-flex h-6 items-center rounded-full bg-gray-100 px-2 text-xs font-medium text-gray-500">{{ __('admin.keyword_opportunities.status_dismissed') }}</span>
                                    @else
                                        <span class="inline-flex h-6 items-center rounded-full bg-blue-50 px-2 text-xs font-medium text-blue-700">{{ __('admin.keyword_opportunities.status_new') }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('admin.keyword_opportunities.col_brand_keyword') }}：{{ $opportunity->brand_keyword }}
                                    @if ($opportunity->source_domain)
                                        · {{ $opportunity->source_domain }}
                                    @endif
                                    @if ($opportunity->last_mined_at)
                                        · {{ $opportunity->last_mined_at->format('Y-m-d H:i') }}
                                    @endif
                                </p>
                                @if (($analysis['intent'] ?? '') !== '' || ($analysis['angle'] ?? '') !== '')
                                    <p class="mt-2 text-xs leading-5 text-gray-600">
                                        @if (($analysis['intent'] ?? '') !== '')
                                            <span class="font-medium">{{ __('admin.keyword_opportunities.analysis') }}：</span>{{ $analysis['intent'] }}
                                        @endif
                                        @if (($analysis['angle'] ?? '') !== '')
                                            <span class="mt-1 block text-gray-500">{{ $analysis['angle'] }}</span>
                                        @endif
                                    </p>
                                @endif
                                @if ($evidence !== [])
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs text-blue-600">{{ __('admin.keyword_opportunities.evidence') }}</summary>
                                        <ul class="mt-1 space-y-1 text-xs text-gray-500">
                                            @foreach ($evidence as $item)
                                                <li>{{ $item['keyword'] ?? '' }} · {{ $item['domain'] ?? '' }} ×{{ (int) ($item['frequency'] ?? 0) }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </div>

                            @if ($opportunity->status === \App\Models\KeywordOpportunity::STATUS_NEW)
                                <div class="flex shrink-0 flex-wrap items-start gap-2">
                                    <form method="POST" action="{{ route('admin.keyword-opportunities.import', ['opportunityId' => (int) $opportunity->id]) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <label class="sr-only" for="library-{{ $opportunity->id }}">{{ __('admin.keyword_opportunities.import_library_label') }}</label>
                                        <select id="library-{{ $opportunity->id }}" name="library_id"
                                                class="h-10 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                                            <option value="">{{ __('admin.keyword_opportunities.import_library_auto') }}</option>
                                            @foreach ($libraries as $library)
                                                <option value="{{ $library->id }}">{{ $library->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex min-h-10 items-center rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                            <i data-lucide="download" class="mr-1 h-3.5 w-3.5"></i>
                                            {{ __('admin.keyword_opportunities.import') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.keyword-opportunities.dismiss', ['opportunityId' => (int) $opportunity->id]) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex min-h-10 items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                            {{ __('admin.keyword_opportunities.dismiss') }}
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                    {{ $opportunities->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
