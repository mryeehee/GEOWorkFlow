@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0 space-y-8">
        <header class="mb-2">
            <div class="sr-only">
                <h1>{{ __('admin.brand.page_title') }}</h1>
                <p>{{ __('admin.brand.subtitle') }}</p>
            </div>
        </header>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <form method="POST" action="{{ route('admin.brand.update') }}" class="xl:col-span-2 space-y-6">
                @csrf
                @method('PUT')

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.brand.group_identity') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">{{ __('admin.brand.group_identity_desc') }}</p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="brand_name" class="block text-sm font-medium text-gray-700">{{ __('admin.brand.field.brand_name') }} <span class="text-red-600">*</span></label>
                            <input id="brand_name" name="brand_name" type="text" maxlength="160" required
                                   value="{{ old('brand_name', $form['brand_name']) }}"
                                   class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.brand.help.brand_name') }}</p>
                            @error('brand_name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                            <div>
                                <label for="brand_aliases" class="block text-sm font-medium text-gray-700">{{ __('admin.brand.field.brand_aliases') }}</label>
                                <textarea id="brand_aliases" name="brand_aliases" rows="4"
                                          class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">{{ old('brand_aliases', $form['brand_aliases']) }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">{{ __('admin.brand.help.one_per_line') }}</p>
                                @error('brand_aliases')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="official_domains" class="block text-sm font-medium text-gray-700">{{ __('admin.brand.field.official_domains') }}</label>
                                <textarea id="official_domains" name="official_domains" rows="4"
                                          class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">{{ old('official_domains', $form['official_domains']) }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">{{ __('admin.brand.help.domains') }}</p>
                                @error('official_domains')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.brand.group_keywords') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">{{ __('admin.brand.group_keywords_desc') }}</p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="brand_keywords" class="block text-sm font-medium text-gray-700">{{ __('admin.brand.field.brand_keywords') }} <span class="text-red-600">*</span></label>
                            <textarea id="brand_keywords" name="brand_keywords" rows="5" required
                                      class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">{{ old('brand_keywords', $form['brand_keywords']) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.brand.help.brand_keywords') }}</p>
                            @error('brand_keywords')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="industries" class="block text-sm font-medium text-gray-700">{{ __('admin.brand.field.industries') }}</label>
                            <textarea id="industries" name="industries" rows="3"
                                      class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">{{ old('industries', $form['industries']) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.brand.help.one_per_line') }}</p>
                            @error('industries')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.brand.group_scope') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">{{ __('admin.brand.group_scope_desc') }}</p>

                    <div class="mt-5">
                        <label for="business_scope" class="block text-sm font-medium text-gray-700">{{ __('admin.brand.field.business_scope') }}</label>
                        <textarea id="business_scope" name="business_scope" rows="6"
                                  class="mt-2 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30">{{ old('business_scope', $form['business_scope']) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.brand.help.business_scope') }}</p>
                        @error('business_scope')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </section>

                <div class="flex items-center justify-end gap-3">
                    <button type="submit" class="inline-flex min-h-10 items-center rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        <i data-lucide="save" class="mr-2 h-4 w-4"></i>
                        {{ __('admin.brand.button.save') }}
                    </button>
                </div>
            </form>

            <aside class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.brand.assets_title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">{{ __('admin.brand.assets_desc') }}</p>
                    <dl class="mt-5 grid grid-cols-2 gap-4">
                        <div class="rounded-md bg-gray-50 p-4">
                            <dt class="text-xs text-gray-500">{{ __('admin.brand.asset.published_articles') }}</dt>
                            <dd class="mt-1 text-xl font-bold text-gray-900">{{ (int) $assets['published_articles'] }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-4">
                            <dt class="text-xs text-gray-500">{{ __('admin.brand.asset.knowledge_bases') }}</dt>
                            <dd class="mt-1 text-xl font-bold text-gray-900">{{ (int) $assets['knowledge_bases'] }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-4">
                            <dt class="text-xs text-gray-500">{{ __('admin.brand.asset.keywords') }}</dt>
                            <dd class="mt-1 text-xl font-bold text-gray-900">{{ (int) $assets['keywords'] }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-4">
                            <dt class="text-xs text-gray-500">{{ __('admin.brand.asset.titles') }}</dt>
                            <dd class="mt-1 text-xl font-bold text-gray-900">{{ (int) $assets['titles'] }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-4">
                            <dt class="text-xs text-gray-500">{{ __('admin.brand.asset.authors') }}</dt>
                            <dd class="mt-1 text-xl font-bold text-gray-900">{{ (int) $assets['authors'] }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 p-4">
                            <dt class="text-xs text-gray-500">{{ __('admin.brand.asset.distribution_channels') }}</dt>
                            <dd class="mt-1 text-xl font-bold text-gray-900">{{ (int) $assets['distribution_channels'] }}</dd>
                        </div>
                    </dl>
                    <div class="mt-5 flex flex-col gap-2">
                        <a href="{{ route('admin.materials.index') }}" class="inline-flex min-h-10 items-center justify-between rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            {{ __('admin.brand.link.materials') }}
                            <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400"></i>
                        </a>
                        <a href="{{ route('admin.knowledge-bases.index') }}" class="inline-flex min-h-10 items-center justify-between rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            {{ __('admin.brand.link.knowledge') }}
                            <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400"></i>
                        </a>
                        <a href="{{ route('admin.distribution.index') }}" class="inline-flex min-h-10 items-center justify-between rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            {{ __('admin.brand.link.distribution') }}
                            <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400"></i>
                        </a>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.brand.usage_title') }}</h2>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-gray-600">
                        <li class="flex gap-3"><i data-lucide="file-text" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.brand.usage_item_generation') }}</li>
                        <li class="flex gap-3"><i data-lucide="eye" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.brand.usage_item_visibility') }}</li>
                        <li class="flex gap-3"><i data-lucide="radio-tower" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>{{ __('admin.brand.usage_item_distribution') }}</li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
@endsection
