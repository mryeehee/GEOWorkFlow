@php
    $analyticsNavigation = [
        ['key' => 'overview', 'route' => 'admin.analytics'],
        ['key' => 'content', 'route' => 'admin.analytics.content'],
        ['key' => 'traffic', 'route' => 'admin.analytics.traffic'],
        ['key' => 'ai_visibility', 'route' => 'admin.analytics.ai-visibility'],
        ['key' => 'content_directions', 'route' => 'admin.analytics.content-directions.index'],
        ['key' => 'leads', 'route' => 'admin.analytics.leads'],
    ];
    if (auth('admin')->user()?->canManageProtectedWorkflows()) {
        $analyticsNavigation[] = ['key' => 'distribution', 'route' => 'admin.analytics.distribution'];
    }
@endphp

<nav data-analytics-navigation class="{{ $analyticsNavigationClass ?? 'mt-6' }} overflow-x-auto" aria-label="{{ __('admin.analytics.navigation.label') }}">
    <div class="flex min-w-max gap-6">
        @foreach ($analyticsNavigation as $item)
            @php $isCurrent = ($analyticsPage ?? 'overview') === $item['key'] || (($analyticsPage ?? '') === 'ai-visibility' && $item['key'] === 'ai_visibility'); @endphp
            <a href="{{ route($item['route']) }}" @if ($isCurrent) aria-current="page" @endif class="relative inline-flex min-h-10 items-center gap-2 border-b-2 px-0.5 pb-2 text-sm font-semibold transition duration-[120ms] motion-reduce:transition-none {{ $isCurrent ? 'border-blue-600 text-gray-950' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800' }}">
                {{ __('admin.analytics.navigation.'.$item['key']) }}
            </a>
        @endforeach
    </div>
</nav>
