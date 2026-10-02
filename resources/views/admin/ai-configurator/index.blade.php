@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="sr-only">
            <h1>{{ __('admin.ai_configurator.heading') }}</h1>
            <p>{{ __('admin.ai_configurator.subtitle') }}</p>
        </div>

        <section class="overflow-hidden rounded-lg bg-white shadow" data-ai-configurator-overview aria-labelledby="ai-configurator-overview-heading">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 id="ai-configurator-overview-heading" class="text-lg font-medium text-gray-900">{{ __('admin.ai_configurator.overview') }}</h2>
            </div>
            <div class="px-6 py-6">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-6 sm:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <dd class="text-2xl font-bold tabular-nums text-gray-900">{{ (int) ($stats['model_count'] ?? 0) }}</dd>
                        <dt class="mt-1 text-sm leading-5 text-gray-500">{{ __('admin.ai_configurator.active_models') }}</dt>
                    </div>
                    <div>
                        <dd class="text-2xl font-bold tabular-nums text-gray-900">{{ (int) ($stats['prompt_count'] ?? 0) }}</dd>
                        <dt class="mt-1 text-sm leading-5 text-gray-500">{{ __('admin.ai_configurator.prompt_templates') }}</dt>
                    </div>
                    <div>
                        <dd class="text-2xl font-bold tabular-nums text-gray-900">{{ number_format((int) ($stats['total_usage'] ?? 0)) }}</dd>
                        <dt class="mt-1 text-sm leading-5 text-gray-500">{{ __('admin.ai_configurator.total_calls') }}</dt>
                    </div>
                    <div>
                        <dd class="text-2xl font-bold tabular-nums text-gray-900">{{ number_format((int) ($stats['today_usage'] ?? 0)) }}</dd>
                        <dt class="mt-1 text-sm leading-5 text-gray-500">{{ __('admin.ai_configurator.today_calls') }}</dt>
                    </div>
                    @if ($showSystemCollectionConfiguration ?? false)
                    <div>
                        <dd class="text-2xl font-bold tabular-nums text-gray-900">{{ (int) ($stats['search_provider_count'] ?? 0) }}</dd>
                        <dt class="mt-1 text-sm leading-5 text-gray-500">{{ __('admin.ai_configurator.active_search_providers') }}</dt>
                    </div>
                    <div>
                        <dd class="text-2xl font-bold tabular-nums {{ (int) ($stats['visibility_failed_runs'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format((int) ($stats['visibility_failed_runs'] ?? 0)) }}</dd>
                        <dt class="mt-1 text-sm leading-5 text-gray-500">{{ __('admin.ai_configurator.visibility_failed_runs') }}</dt>
                    </div>
                    @endif
                </dl>
            </div>
        </section>

        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4" data-ai-configurator-modules>
            <a href="{{ route('admin.ai-models.index') }}" class="flex items-start gap-4 rounded-lg bg-white p-5 shadow transition hover:bg-gray-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                    <i data-lucide="cpu" class="h-5 w-5"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.ai_configurator.models_title') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-gray-500">{{ __('admin.ai_configurator.models_desc') }}</span>
                    <span class="mt-3 inline-flex items-center text-sm font-semibold text-blue-600">
                        {{ __('admin.ai_configurator.models_action') }}
                        <i data-lucide="chevron-right" class="ml-1 h-4 w-4"></i>
                    </span>
                </span>
            </a>

            <a href="{{ route('admin.ai-prompts') }}" class="flex items-start gap-4 rounded-lg bg-white p-5 shadow transition hover:bg-gray-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                    <i data-lucide="message-square" class="h-5 w-5"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.ai_configurator.prompts_title') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-gray-500">{{ __('admin.ai_configurator.prompts_desc') }}</span>
                    <span class="mt-3 inline-flex items-center text-sm font-semibold text-blue-600">
                        {{ __('admin.ai_configurator.prompts_action') }}
                        <i data-lucide="chevron-right" class="ml-1 h-4 w-4"></i>
                    </span>
                </span>
            </a>

            <a href="{{ route('admin.ai-special-prompts') }}" class="flex items-start gap-4 rounded-lg bg-white p-5 shadow transition hover:bg-gray-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                    <i data-lucide="settings" class="h-5 w-5"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.ai_configurator.special_title') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-gray-500">{{ __('admin.ai_configurator.special_desc') }}</span>
                    <span class="mt-3 inline-flex items-center text-sm font-semibold text-blue-600">
                        {{ __('admin.ai_configurator.special_action') }}
                        <i data-lucide="chevron-right" class="ml-1 h-4 w-4"></i>
                    </span>
                </span>
            </a>

            @if ($showSystemCollectionConfiguration ?? false)
            <a href="{{ route('admin.ai-source-providers.index') }}" class="flex items-start gap-4 rounded-lg bg-white p-5 shadow transition hover:bg-gray-50">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                    <i data-lucide="search-check" class="h-5 w-5"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.ai_configurator.search_title') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-gray-500">{{ __('admin.ai_configurator.search_desc') }}</span>
                    <span class="mt-3 inline-flex items-center text-sm font-semibold text-blue-600">
                        {{ __('admin.ai_configurator.search_action') }}
                        <i data-lucide="chevron-right" class="ml-1 h-4 w-4"></i>
                    </span>
                </span>
            </a>
            @endif
        </div>

        <section class="mt-8 rounded-lg bg-white p-5 shadow">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                <i data-lucide="info" class="h-4 w-4 text-gray-400"></i>
                {{ __('admin.ai_configurator.help_title') }}
            </h2>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm leading-6 text-gray-500">
                <li>{{ __('admin.ai_configurator.help_models') }}</li>
                @if ($showSystemCollectionConfiguration ?? false)
                    <li>{{ __('admin.ai_configurator.help_search_providers') }}</li>
                @endif
                <li>{{ __('admin.ai_configurator.help_content_prompts') }}</li>
                <li>{{ __('admin.ai_configurator.help_special_prompts') }}</li>
                <li>{{ __('admin.ai_configurator.help_pipeline') }}</li>
            </ul>
        </section>
    </div>
@endsection
