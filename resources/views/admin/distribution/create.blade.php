@extends('admin.layouts.app')

@php
    $requestedChannelType = (string) request()->query('channel_type');
    $channelType = old('channel_type', in_array($requestedChannelType, ['geoflow_agent', 'wordpress_rest', 'generic_http_api', 'cn_platform'], true) ? $requestedChannelType : 'geoflow_agent');
    $frontMode = old('front_mode', 'static');
    $themes = $availableThemes ?? [];
    $selectedTheme = old('template_key', '');
    $visibleThemeLimit = 6;
    $themeOptions = array_merge([[
        'id' => '',
        'name' => __('admin.site_settings.theme.default_name'),
        'version' => '',
        'description' => __('admin.site_settings.theme.default_desc'),
    ]], $themes);
    $collapsedThemeCount = collect($themeOptions)
        ->filter(fn (array $themeOption, int $themeIndex): bool => $themeIndex >= $visibleThemeLimit && $selectedTheme !== $themeOption['id'])
        ->count();
    $genericAuthType = old('generic_auth_type', 'bearer');
    $cnPresets = $cnPlatformPresets ?? [];
    $requestedPlatform = (string) request()->query('cn_platform');
    $cnPlatformDefault = collect($cnPresets)->contains('key', $requestedPlatform) ? $requestedPlatform : 'zhihu';
    $cnPlatform = old('cn_platform', $cnPlatformDefault);
    $cnModeSupport = collect($cnPresets)->mapWithKeys(fn (array $preset): array => [$preset['key'] => array_values((array) ($preset['modes'] ?? []))])->all();
    $cnAuthMode = old('cn_auth_mode', '');
    $cnDefaultModes = $cnModeSupport[$cnPlatform] ?? ['browser_assist'];
    if ($cnAuthMode === '' || ! in_array($cnAuthMode, $cnDefaultModes, true)) {
        $cnAuthMode = $cnAuthMode === '' ? ($cnDefaultModes[0] ?? 'browser_assist') : $cnAuthMode;
    }
    $cnModeOptions = [
        'browser_assist' => __('admin.distribution.cn.mode_browser_assist'),
        'oauth2' => __('admin.distribution.cn.mode_oauth2'),
        'api' => __('admin.distribution.cn.mode_api'),
    ];
    $cnTierLabels = [
        1 => __('admin.distribution.cn.tier_1'),
        2 => __('admin.distribution.cn.tier_2'),
        3 => __('admin.distribution.cn.tier_3'),
    ];
    $genericEndpointMethods = [
        'health' => ['GET', 'POST'],
        'publish' => ['POST', 'PUT', 'PATCH'],
        'update' => ['POST', 'PUT', 'PATCH'],
        'delete' => ['DELETE', 'POST'],
        'settings' => ['POST', 'PUT', 'PATCH'],
    ];
@endphp

@section('content')
    <div class="px-4 sm:px-0">
        <div class="mb-8 flex items-center space-x-4">
            <a href="{{ route('admin.distribution.index') }}" aria-label="{{ __('admin.common.back') }}" class="text-gray-400 hover:text-gray-600">
                <i data-lucide="arrow-left" class="h-5 w-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.distribution.create_heading') }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ __('admin.distribution.create_subtitle') }}</p>
            </div>
        </div>

        <div class="rounded-lg bg-white shadow">
            <div class="px-6 py-6">
                <form method="POST" action="{{ route('admin.distribution.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.field.name') }} *</label>
                        <input id="name" name="name" type="text" required value="{{ old('name') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="{{ __('admin.distribution.placeholder.name') }}">
                    </div>

                    <fieldset class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <legend class="text-sm font-medium text-gray-900">{{ __('admin.distribution.field.channel_type') }}</legend>
                        <p class="mt-1 text-sm text-gray-600">{{ __('admin.distribution.help.channel_type') }}</p>
                        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300">
                                <input type="radio" name="channel_type" value="geoflow_agent" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($channelType === 'geoflow_agent')>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.distribution.channel_type.geoflow_agent') }}</span>
                                    <span class="mt-1 block text-sm text-gray-600">{{ __('admin.distribution.channel_type.geoflow_agent_desc') }}</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300">
                                <input type="radio" name="channel_type" value="wordpress_rest" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($channelType === 'wordpress_rest')>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.distribution.channel_type.wordpress_rest') }}</span>
                                    <span class="mt-1 block text-sm text-gray-600">{{ __('admin.distribution.channel_type.wordpress_rest_desc') }}</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300">
                                <input type="radio" name="channel_type" value="generic_http_api" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($channelType === 'generic_http_api')>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.distribution.channel_type.generic_http_api') }}</span>
                                    <span class="mt-1 block text-sm text-gray-600">{{ __('admin.distribution.channel_type.generic_http_api_desc') }}</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300">
                                <input type="radio" name="channel_type" value="cn_platform" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($channelType === 'cn_platform')>
                                <span>
                                    <span class="block text-sm font-semibold text-gray-900">{{ __('admin.distribution.channel_type.cn_platform') }}</span>
                                    <span class="mt-1 block text-sm text-gray-600">{{ __('admin.distribution.channel_type.cn_platform_desc') }}</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="domain" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.field.domain') }} *</label>
                            <input id="domain" name="domain" type="text" required value="{{ old('domain') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="example.com">
                        </div>
                        <div>
                            <label for="endpoint_url" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.field.endpoint_url') }} *</label>
                            <input id="endpoint_url" name="endpoint_url" type="text" required value="{{ old('endpoint_url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="{{ __('admin.distribution.placeholder.endpoint_url') }}">
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.distribution.help.endpoint_url') }}</p>
                        </div>
                    </div>

                    <div data-channel-type-panel="cn_platform" @class(['rounded-lg border border-gray-200 bg-gray-50 p-5', 'hidden' => $channelType !== 'cn_platform'])>
                        <div class="mb-5">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('admin.distribution.cn.section_title') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600">{{ __('admin.distribution.cn.section_desc') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            @foreach ($cnPresets as $cnPreset)
                                <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300" data-cn-platform-card="{{ $cnPreset['key'] }}">
                                    <input type="radio" name="cn_platform" value="{{ $cnPreset['key'] }}" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($cnPlatform === $cnPreset['key'])>
                                    <span class="min-w-0">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-semibold text-gray-900">{{ $cnPreset['name'] }}</span>
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $cnTierLabels[(int) ($cnPreset['tier'] ?? 3)] ?? '' }}</span>
                                        </span>
                                        <span class="mt-1 block text-xs leading-5 text-gray-600">{{ $cnPreset['note'] ?? '' }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            <span class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.auth_mode') }} *</span>
                            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-3">
                                @foreach ($cnModeOptions as $cnModeKey => $cnModeLabel)
                                    <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300" data-cn-mode-card="{{ $cnModeKey }}">
                                        <input type="radio" name="cn_auth_mode" value="{{ $cnModeKey }}" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($cnAuthMode === $cnModeKey)>
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900">{{ $cnModeLabel }}</span>
                                            <span class="mt-1 block text-xs leading-5 text-gray-600">{{ __('admin.distribution.cn.'.$cnModeKey.'_desc') }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4" data-cn-mode-panel="oauth2">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.distribution.cn.oauth_section') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-gray-600">{{ __('admin.distribution.cn.oauth_desc') }}</p>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="cn_secret_app_key" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.app_key') }}</label>
                                    <input id="cn_secret_app_key" name="cn_secret_app_key" type="text" value="{{ old('cn_secret_app_key') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autocomplete="off">
                                </div>
                                <div>
                                    <label for="cn_secret_app_secret" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.app_secret') }}</label>
                                    <input id="cn_secret_app_secret" name="cn_secret_app_secret" type="password" value="{{ old('cn_secret_app_secret') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autocomplete="new-password">
                                </div>
                                <div>
                                    <label for="cn_secret_token" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.access_token') }}</label>
                                    <input id="cn_secret_token" name="cn_secret_token" type="password" value="{{ old('cn_secret_token') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autocomplete="new-password">
                                </div>
                                <div>
                                    <label for="cn_secret_uid" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.uid') }}</label>
                                    <input id="cn_secret_uid" name="cn_secret_uid" type="text" value="{{ old('cn_secret_uid') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autocomplete="off">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4" data-cn-mode-panel="api">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.distribution.cn.api_section') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-gray-600">{{ __('admin.distribution.cn.api_desc') }}</p>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="cn_api_publish_url" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.api_publish_url') }} *</label>
                                    <input id="cn_api_publish_url" name="cn_api_publish_url" type="text" value="{{ old('cn_api_publish_url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="https://example.com/api/articles">
                                </div>
                                <div>
                                    <label for="cn_api_update_url" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.api_update_url') }}</label>
                                    <input id="cn_api_update_url" name="cn_api_update_url" type="text" value="{{ old('cn_api_update_url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="https://example.com/api/articles/{remote_id}">
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label for="cn_api_method" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.api_method') }}</label>
                                    <select id="cn_api_method" name="cn_api_method" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @foreach (['POST', 'PUT', 'PATCH'] as $cnMethod)
                                            <option value="{{ $cnMethod }}" @selected(old('cn_api_method', 'POST') === $cnMethod)>{{ $cnMethod }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="cn_api_auth_style" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.api_auth_style') }}</label>
                                    <select id="cn_api_auth_style" name="cn_api_auth_style" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="header_bearer" @selected(old('cn_api_auth_style', 'header_bearer') === 'header_bearer')>{{ __('admin.distribution.cn.auth_style_header_bearer') }}</option>
                                        <option value="header_key" @selected(old('cn_api_auth_style') === 'header_key')>{{ __('admin.distribution.cn.auth_style_header_key') }}</option>
                                        <option value="query_token" @selected(old('cn_api_auth_style') === 'query_token')>{{ __('admin.distribution.cn.auth_style_query_token') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="cn_api_auth_header" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.api_auth_header') }}</label>
                                    <input id="cn_api_auth_header" name="cn_api_auth_header" type="text" value="{{ old('cn_api_auth_header', 'X-API-Key') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="cn_secret_token_api" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.api_token') }} *</label>
                                    <input id="cn_secret_token_api" name="cn_secret_token_api" type="password" value="{{ old('cn_secret_token_api') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autocomplete="new-password">
                                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.distribution.cn.api_token_help') }}</p>
                                </div>
                                <div>
                                    <label for="cn_content_format" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.content_format') }}</label>
                                    <select id="cn_content_format" name="cn_content_format" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="html" @selected(old('cn_content_format', 'html') === 'html')>{{ __('admin.distribution.cn.format_html') }}</option>
                                        <option value="markdown" @selected(old('cn_content_format') === 'markdown')>{{ __('admin.distribution.cn.format_markdown') }}</option>
                                        <option value="text" @selected(old('cn_content_format') === 'text')>{{ __('admin.distribution.cn.format_text') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label for="cn_api_title_field" class="block text-xs font-medium text-gray-600">{{ __('admin.distribution.cn.title_field') }}</label>
                                    <input id="cn_api_title_field" name="cn_api_title_field" type="text" value="{{ old('cn_api_title_field', 'title') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label for="cn_api_content_field" class="block text-xs font-medium text-gray-600">{{ __('admin.distribution.cn.content_field') }}</label>
                                    <input id="cn_api_content_field" name="cn_api_content_field" type="text" value="{{ old('cn_api_content_field', 'content') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label for="cn_api_url_field" class="block text-xs font-medium text-gray-600">{{ __('admin.distribution.cn.url_field') }}</label>
                                    <input id="cn_api_url_field" name="cn_api_url_field" type="text" value="{{ old('cn_api_url_field', 'source_url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label for="cn_api_remote_id_path" class="block text-xs font-medium text-gray-600">{{ __('admin.distribution.cn.remote_id_path') }}</label>
                                    <input id="cn_api_remote_id_path" name="cn_api_remote_id_path" type="text" value="{{ old('cn_api_remote_id_path', 'id') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label for="cn_api_remote_url_path" class="block text-xs font-medium text-gray-600">{{ __('admin.distribution.cn.remote_url_path') }}</label>
                                    <input id="cn_api_remote_url_path" name="cn_api_remote_url_path" type="text" value="{{ old('cn_api_remote_url_path', 'url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4" data-cn-mode-panel="browser_assist">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.distribution.cn.browser_section') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-gray-600">{{ __('admin.distribution.cn.browser_desc') }}</p>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label for="cn_mp_persona_id" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.persona') }}</label>
                                    <select id="cn_mp_persona_id" name="cn_mp_persona_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">{{ __('admin.distribution.cn.persona_auto') }}</option>
                                        @foreach (($cnPersonas ?? []) as $cnPersona)
                                            <option value="{{ $cnPersona->id }}" @selected((string) old('cn_mp_persona_id') === (string) $cnPersona->id)>{{ $cnPersona->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.distribution.cn.persona_help') }}</p>
                                </div>
                                <div>
                                    <label for="cn_write_url" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.write_url') }}</label>
                                    <input id="cn_write_url" name="cn_write_url" type="text" value="{{ old('cn_write_url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="https://zhuanlan.zhihu.com/write">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label for="cn_post_status" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.post_status') }}</label>
                                <select id="cn_post_status" name="cn_post_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="draft" @selected(old('cn_post_status', 'draft') === 'draft')>{{ __('admin.distribution.cn.post_status_draft') }}</option>
                                    <option value="publish" @selected(old('cn_post_status') === 'publish')>{{ __('admin.distribution.cn.post_status_publish') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="cn_title_limit" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.title_limit') }}</label>
                                <input id="cn_title_limit" name="cn_title_limit" type="number" min="10" max="200" value="{{ old('cn_title_limit') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="cn_summary_limit" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.summary_limit') }}</label>
                                <input id="cn_summary_limit" name="cn_summary_limit" type="number" min="20" max="1000" value="{{ old('cn_summary_limit') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-center md:gap-8">
                            <div class="flex items-center gap-2">
                                <input type="hidden" name="cn_append_source_link" value="0">
                                <input id="cn_append_source_link" name="cn_append_source_link" type="checkbox" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked(old('cn_append_source_link', '1'))>
                                <label for="cn_append_source_link" class="text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.append_source_link') }}</label>
                            </div>
                            <div class="md:w-64">
                                <label for="cn_image_strategy" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.cn.image_strategy') }}</label>
                                <select id="cn_image_strategy" name="cn_image_strategy" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="keep_original" @selected(old('cn_image_strategy', 'keep_original') === 'keep_original')>{{ __('admin.distribution.cn.image_keep_original') }}</option>
                                    <option value="omit" @selected(old('cn_image_strategy') === 'omit')>{{ __('admin.distribution.cn.image_omit') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div data-channel-type-panel="wordpress_rest" @class(['rounded-lg border border-gray-200 bg-gray-50 p-5', 'hidden' => $channelType !== 'wordpress_rest'])>
                        <div class="mb-5">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('admin.distribution.wordpress.section_title') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600">{{ __('admin.distribution.wordpress.section_desc') }}</p>
                        </div>
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label for="wordpress_username" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.wordpress.username') }}</label>
                                <input id="wordpress_username" name="wordpress_username" type="text" value="{{ old('wordpress_username') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="editor">
                            </div>
                            <div>
                                <label for="wordpress_application_password" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.wordpress.application_password') }}</label>
                                <input id="wordpress_application_password" name="wordpress_application_password" type="password" value="{{ old('wordpress_application_password') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autocomplete="new-password">
                                <p class="mt-1 text-xs text-gray-500">{{ __('admin.distribution.wordpress.application_password_help') }}</p>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label for="wordpress_post_status" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.wordpress.post_status') }}</label>
                                <select id="wordpress_post_status" name="wordpress_post_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach (['publish', 'draft', 'pending', 'private'] as $status)
                                        <option value="{{ $status }}" @selected(old('wordpress_post_status', 'draft') === $status)>{{ __('admin.distribution.wordpress.post_status_'.$status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="wordpress_image_strategy" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.wordpress.image_strategy') }}</label>
                                <select id="wordpress_image_strategy" name="wordpress_image_strategy" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="upload_to_media" @selected(old('wordpress_image_strategy', 'upload_to_media') === 'upload_to_media')>{{ __('admin.distribution.wordpress.image_upload_to_media') }}</option>
                                    <option value="keep_original" @selected(old('wordpress_image_strategy') === 'keep_original')>{{ __('admin.distribution.wordpress.image_keep_original') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <label for="wordpress_category_strategy" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.wordpress.category_strategy') }}</label>
                                <select id="wordpress_category_strategy" name="wordpress_category_strategy" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="match_or_create" @selected(old('wordpress_category_strategy', 'match_or_create') === 'match_or_create')>{{ __('admin.distribution.wordpress.category_match_or_create') }}</option>
                                    <option value="match_only" @selected(old('wordpress_category_strategy') === 'match_only')>{{ __('admin.distribution.wordpress.category_match_only') }}</option>
                                    <option value="fixed" @selected(old('wordpress_category_strategy') === 'fixed')>{{ __('admin.distribution.wordpress.category_fixed') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="wordpress_fixed_category" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.wordpress.fixed_category') }}</label>
                                <input id="wordpress_fixed_category" name="wordpress_fixed_category" type="text" value="{{ old('wordpress_fixed_category') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="1 或 News">
                            </div>
                            <div>
                                <label for="wordpress_tag_strategy" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.wordpress.tag_strategy') }}</label>
                                <select id="wordpress_tag_strategy" name="wordpress_tag_strategy" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="keywords_to_tags" @selected(old('wordpress_tag_strategy', 'keywords_to_tags') === 'keywords_to_tags')>{{ __('admin.distribution.wordpress.tag_keywords_to_tags') }}</option>
                                    <option value="disabled" @selected(old('wordpress_tag_strategy') === 'disabled')>{{ __('admin.distribution.wordpress.tag_disabled') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div data-channel-type-panel="generic_http_api" @class(['rounded-lg border border-gray-200 bg-gray-50 p-5', 'hidden' => $channelType !== 'generic_http_api'])>
                        <div class="mb-5">
                            <h2 class="text-lg font-medium text-gray-900">{{ __('admin.distribution.generic.section_title') }}</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600">{{ __('admin.distribution.generic.section_desc') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <label for="generic_auth_type" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.auth_type') }}</label>
                                <select id="generic_auth_type" name="generic_auth_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach (['bearer', 'none', 'basic', 'header_key', 'hmac'] as $authType)
                                        <option value="{{ $authType }}" @selected($genericAuthType === $authType)>{{ __('admin.distribution.generic.auth_'.$authType) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div data-generic-auth-row="basic">
                                <label for="generic_basic_username" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.basic_username') }}</label>
                                <input id="generic_basic_username" name="generic_basic_username" type="text" value="{{ old('generic_basic_username') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="api-user">
                            </div>
                            <div data-generic-auth-secret>
                                <label for="generic_secret" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.secret') }}</label>
                                <input id="generic_secret" name="generic_secret" type="password" value="{{ old('generic_secret') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" autocomplete="new-password">
                                <p class="mt-1 text-xs text-gray-500">{{ __('admin.distribution.generic.secret_help') }}</p>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div data-generic-auth-row="header_key">
                                <label for="generic_header_name" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.header_name') }}</label>
                                <input id="generic_header_name" name="generic_header_name" type="text" value="{{ old('generic_header_name', 'X-API-Key') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="generic_timeout_seconds" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.timeout_seconds') }}</label>
                                <input id="generic_timeout_seconds" name="generic_timeout_seconds" type="number" min="5" max="120" value="{{ old('generic_timeout_seconds', 30) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="generic_success_statuses" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.success_statuses') }}</label>
                                <input id="generic_success_statuses" name="generic_success_statuses" type="text" value="{{ old('generic_success_statuses', '200,201,202,204') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.distribution.generic.endpoint_section') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ __('admin.distribution.generic.endpoint_help') }}</p>
                            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach ([
                                    ['generic_health_method', 'generic_health_path', 'health', 'GET', '/health'],
                                    ['generic_publish_method', 'generic_publish_path', 'publish', 'POST', '/articles'],
                                    ['generic_update_method', 'generic_update_path', 'update', 'POST', '/articles/{remote_id}'],
                                    ['generic_delete_method', 'generic_delete_path', 'delete', 'DELETE', '/articles/{remote_id}'],
                                    ['generic_settings_method', 'generic_settings_path', 'settings', 'POST', ''],
                                ] as [$methodName, $pathName, $labelKey, $defaultMethod, $defaultPath])
                                    <div class="grid grid-cols-3 gap-3">
                                        <div>
                                            <label for="{{ $methodName }}" class="block text-xs font-medium text-gray-600">{{ __('admin.distribution.generic.endpoint_'.$labelKey) }}</label>
                                            <select id="{{ $methodName }}" name="{{ $methodName }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @foreach ($genericEndpointMethods[$labelKey] as $method)
                                                    <option value="{{ $method }}" @selected(old($methodName, $defaultMethod) === $method)>{{ $method }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-span-2">
                                            <label for="{{ $pathName }}" class="block text-xs font-medium text-gray-600">{{ __('admin.distribution.generic.path') }}</label>
                                            <input id="{{ $pathName }}" name="{{ $pathName }}" type="text" value="{{ old($pathName, $defaultPath) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div>
                                <label for="generic_payload_wrapper" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.payload_wrapper') }}</label>
                                <select id="generic_payload_wrapper" name="generic_payload_wrapper" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="none" @selected(old('generic_payload_wrapper', 'none') === 'none')>{{ __('admin.distribution.generic.wrapper_none') }}</option>
                                    <option value="data" @selected(old('generic_payload_wrapper') === 'data')>{{ __('admin.distribution.generic.wrapper_data') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="generic_remote_id_path" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.remote_id_path') }}</label>
                                <input id="generic_remote_id_path" name="generic_remote_id_path" type="text" value="{{ old('generic_remote_id_path', 'id') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="data.id">
                            </div>
                            <div>
                                <label for="generic_remote_url_path" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.generic.remote_url_path') }}</label>
                                <input id="generic_remote_url_path" name="generic_remote_url_path" type="text" value="{{ old('generic_remote_url_path', 'url') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="data.url">
                            </div>
                        </div>
                    </div>

                    <div data-channel-type-panel="geoflow_agent" @class(['space-y-6', 'hidden' => $channelType !== 'geoflow_agent'])>
                        <fieldset class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <legend class="text-sm font-medium text-gray-900">{{ __('admin.site_settings.theme.section_title') }}</legend>
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.distribution.remote_site.theme_help') }}</p>
                                </div>
                                @if ($collapsedThemeCount > 0)
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                        data-distribution-theme-toggle
                                        data-expand-label="{{ __('admin.distribution.remote_site.template_expand_more', ['count' => $collapsedThemeCount]) }}"
                                        data-collapse-label="{{ __('admin.distribution.remote_site.template_collapse') }}"
                                        aria-expanded="false"
                                    >
                                        {{ __('admin.distribution.remote_site.template_expand_more', ['count' => $collapsedThemeCount]) }}
                                    </button>
                                @endif
                            </div>
                            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                                @foreach ($themeOptions as $themeIndex => $themeOption)
                                    @php($isCollapsedTheme = $themeIndex >= $visibleThemeLimit && $selectedTheme !== $themeOption['id'])
                                    <label
                                        @class([
                                            'flex cursor-pointer gap-3 rounded-lg border border-gray-200 bg-white p-4 hover:border-blue-200',
                                            'hidden' => $isCollapsedTheme,
                                        ])
                                        data-distribution-theme-card
                                        data-distribution-theme-collapsed="{{ $themeIndex >= $visibleThemeLimit ? 'true' : 'false' }}"
                                    >
                                        <input type="radio" name="template_key" value="{{ $themeOption['id'] }}" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($selectedTheme === $themeOption['id'])>
                                        <span class="min-w-0">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-gray-900">{{ $themeOption['name'] }}</span>
                                                @if (($themeOption['version'] ?? '') !== '')
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ __('admin.site_settings.theme.version_badge', ['version' => $themeOption['version']]) }}</span>
                                                @endif
                                            </span>
                                            <span class="mt-1 block text-sm leading-6 text-gray-600">{{ ($themeOption['description'] ?? '') !== '' ? $themeOption['description'] : __('admin.site_settings.theme.no_description') }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <legend class="text-sm font-medium text-gray-900">{{ __('admin.distribution.field.front_mode') }}</legend>
                            <p class="mt-1 text-sm text-gray-600">{{ __('admin.distribution.help.front_mode') }}</p>
                            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300">
                                    <input type="radio" name="front_mode" value="static" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($frontMode === 'static')>
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900">{{ __('admin.distribution.front_mode.static') }}</span>
                                        <span class="mt-1 block text-sm text-gray-600">{{ __('admin.distribution.front_mode.static_desc') }}</span>
                                    </span>
                                </label>
                                <label class="flex cursor-pointer gap-3 rounded-md border border-gray-200 bg-white p-4 hover:border-blue-300">
                                    <input type="radio" name="front_mode" value="rewrite" class="mt-1 text-blue-600 focus:ring-blue-500" @checked($frontMode === 'rewrite')>
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900">{{ __('admin.distribution.front_mode.rewrite') }}</span>
                                        <span class="mt-1 block text-sm text-gray-600">{{ __('admin.distribution.front_mode.rewrite_desc') }}</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">{{ __('admin.distribution.field.status') }}</label>
                            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="active" @selected(old('status', 'active') === 'active')>{{ __('admin.distribution.status.active') }}</option>
                                <option value="paused" @selected(old('status') === 'paused')>{{ __('admin.distribution.status.paused') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">{{ __('admin.common.description') }}</label>
                        <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="{{ __('admin.distribution.placeholder.description') }}">{{ old('description') }}</textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.distribution.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('admin.button.cancel') }}</a>
                        <button type="submit" class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <i data-lucide="key-round" class="mr-2 h-4 w-4"></i>
                            {{ __('admin.distribution.button.save_and_generate_secret') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('change', function (event) {
            if (event.target.matches('[name="channel_type"]')) {
                document.querySelectorAll('[data-channel-type-panel]').forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.dataset.channelTypePanel !== event.target.value);
                });
            }
            if (event.target.matches('#generic_auth_type')) {
                toggleGenericAuthFields();
            }
            if (event.target.matches('[name="cn_platform"], [name="cn_auth_mode"]')) {
                refreshCnPlatformFields();
            }
        });
        var cnModeSupport = @json($cnModeSupport);
        function refreshCnPlatformFields() {
            var platformInput = document.querySelector('[name="cn_platform"]:checked');
            if (!platformInput) {
                return;
            }
            var modes = cnModeSupport[platformInput.value] || [];
            document.querySelectorAll('[data-cn-mode-card]').forEach(function (card) {
                card.classList.toggle('hidden', modes.indexOf(card.dataset.cnModeCard) === -1);
            });
            var active = document.querySelector('[name="cn_auth_mode"]:checked');
            if (active && modes.length && modes.indexOf(active.value) === -1) {
                active.checked = false;
                active = null;
            }
            if (!active && modes.length) {
                var first = document.querySelector('[name="cn_auth_mode"][value="' + modes[0] + '"]');
                if (first) {
                    first.checked = true;
                    active = first;
                }
            }
            document.querySelectorAll('[data-cn-mode-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', !active || panel.dataset.cnModePanel !== active.value);
            });
        }
        refreshCnPlatformFields();
        var themeToggle = document.querySelector('[data-distribution-theme-toggle]');
        var themeExpanded = false;
        function refreshDistributionThemeCards() {
            document.querySelectorAll('[data-distribution-theme-card]').forEach(function (card) {
                var isCollapsed = card.dataset.distributionThemeCollapsed === 'true';
                var checkedInput = card.querySelector('input[type="radio"]:checked');
                card.classList.toggle('hidden', isCollapsed && !themeExpanded && !checkedInput);
            });
            if (themeToggle) {
                themeToggle.textContent = themeExpanded ? themeToggle.dataset.collapseLabel : themeToggle.dataset.expandLabel;
                themeToggle.setAttribute('aria-expanded', themeExpanded ? 'true' : 'false');
            }
        }
        if (themeToggle) {
            themeToggle.addEventListener('click', function () {
                themeExpanded = !themeExpanded;
                refreshDistributionThemeCards();
            });
        }
        function toggleGenericAuthFields() {
            var select = document.getElementById('generic_auth_type');
            if (!select) {
                return;
            }
            var authType = select.value;
            document.querySelectorAll('[data-generic-auth-row]').forEach(function (field) {
                field.classList.toggle('hidden', field.dataset.genericAuthRow !== authType);
            });
            document.querySelectorAll('[data-generic-auth-secret]').forEach(function (field) {
                field.classList.toggle('hidden', authType === 'none');
            });
        }
        toggleGenericAuthFields();
        refreshDistributionThemeCards();
    </script>
@endsection
