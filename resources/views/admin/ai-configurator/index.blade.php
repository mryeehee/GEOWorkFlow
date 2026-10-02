@extends('admin.layouts.app')

@php
    $keyGuides = __('admin.ai_key_guides.providers');
    $checklist = [
        [
            'label' => __('admin.ai_configurator.checklist_chat'),
            'desc' => __('admin.ai_configurator.checklist_chat_desc'),
            'ready' => (int) ($stats['chat_model_count'] ?? 0) > 0,
            'action_label' => __('admin.ai_configurator.action_create_model'),
            'action_url' => route('admin.ai-models.create'),
            'guides' => ['deepseek', 'openai', 'zhipu'],
        ],
        [
            'label' => __('admin.ai_configurator.checklist_embedding'),
            'desc' => __('admin.ai_configurator.checklist_embedding_desc'),
            'ready' => (int) ($stats['embedding_model_count'] ?? 0) > 0,
            'action_label' => __('admin.ai_configurator.action_create_model'),
            'action_url' => route('admin.ai-models.create'),
            'guides' => ['volcengine_ark', 'openai', 'zhipu'],
        ],
        [
            'label' => __('admin.ai_configurator.checklist_prompts'),
            'desc' => __('admin.ai_configurator.checklist_prompts_desc'),
            'ready' => (int) ($stats['prompt_count'] ?? 0) > 0,
            'action_label' => __('admin.ai_configurator.action_configure'),
            'action_url' => route('admin.ai-prompts'),
            'guides' => [],
        ],
    ];
    if ($showSystemCollectionConfiguration ?? false) {
        $checklist[] = [
            'label' => __('admin.ai_configurator.checklist_sources'),
            'desc' => __('admin.ai_configurator.checklist_sources_desc'),
            'ready' => (int) ($stats['search_provider_count'] ?? 0) > 0,
            'action_label' => __('admin.ai_configurator.action_configure'),
            'action_url' => route('admin.ai-source-providers.index'),
            'guides' => ['volcengine_ark', 'deepseek'],
        ];
    }
    $quickSteps = [
        ['icon' => 'key-round', 'title' => __('admin.ai_configurator.quick_step_apply'), 'desc' => __('admin.ai_configurator.quick_step_apply_desc')],
        ['icon' => 'wand-sparkles', 'title' => __('admin.ai_configurator.quick_step_preset'), 'desc' => __('admin.ai_configurator.quick_step_preset_desc')],
        ['icon' => 'plug-zap', 'title' => __('admin.ai_configurator.quick_step_test'), 'desc' => __('admin.ai_configurator.quick_step_test_desc')],
    ];
@endphp

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

        <section class="mt-6 overflow-hidden rounded-lg bg-white shadow" data-ai-configurator-setup-guide aria-labelledby="ai-configurator-setup-heading">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 id="ai-configurator-setup-heading" class="text-lg font-medium text-gray-900">{{ __('admin.ai_configurator.setup_guide_title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-gray-500">{{ __('admin.ai_configurator.setup_guide_subtitle') }}</p>
            </div>

            <div class="px-6 py-6">
                <ol class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-ai-configurator-quick-steps>
                    @foreach ($quickSteps as $index => $step)
                        <li class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <i data-lucide="{{ $step['icon'] }}" class="h-4 w-4 text-blue-600"></i>
                                {{ $step['title'] }}
                            </span>
                            <p class="mt-1.5 text-sm leading-6 text-gray-500">{{ $step['desc'] }}</p>
                        </li>
                    @endforeach
                </ol>

                <ul class="mt-6 divide-y divide-gray-200 rounded-lg border border-gray-200" data-ai-configurator-checklist>
                    @foreach ($checklist as $item)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $item['ready'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                <i data-lucide="{{ $item['ready'] ? 'check' : 'alert-triangle' }}" class="h-3.5 w-3.5"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-900">{{ $item['label'] }}</span>
                                <span class="mt-0.5 block text-xs leading-5 text-gray-500">{{ $item['desc'] }}</span>
                                @if (! empty($item['guides']))
                                    <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        @foreach ($item['guides'] as $guideKey)
                                            @php $guide = $keyGuides[$guideKey] ?? null; @endphp
                                            @if ($guide)
                                                <a href="{{ $guide['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline">
                                                    <i data-lucide="external-link" class="h-3 w-3"></i>
                                                    {{ $guide['label'] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                            <span class="shrink-0 text-xs font-semibold {{ $item['ready'] ? 'text-green-700' : 'text-amber-700' }}">
                                {{ $item['ready'] ? __('admin.ai_configurator.status_ready') : __('admin.ai_configurator.status_pending') }}
                            </span>
                            <a href="{{ $item['action_url'] }}" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                                {{ $item['action_label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <details class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4" data-ai-configurator-key-guides>
                    <summary class="cursor-pointer text-sm font-semibold text-gray-900">{{ __('admin.ai_key_guides.title') }}</summary>
                    <p class="mt-1.5 text-xs leading-5 text-gray-500">{{ __('admin.ai_key_guides.hint') }}</p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($keyGuides as $guide)
                            <li class="text-sm leading-6 text-gray-600">
                                <a href="{{ $guide['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-600 hover:text-blue-700 hover:underline">{{ $guide['label'] }}</a>
                                <span class="text-gray-400">·</span>
                                {{ $guide['hint'] }}
                            </li>
                        @endforeach
                    </ul>
                </details>
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
