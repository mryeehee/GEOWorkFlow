<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DistributionChannel;
use App\Support\AdminWeb;
use App\Support\GeoFlow\AiCitationSourceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiCitationSourceController extends Controller
{
    public function index(): View
    {
        $disabled = AiCitationSourceRegistry::disabledKeys();
        $coverage = AiCitationSourceRegistry::coverageFor(
            DistributionChannel::query()->orderBy('id')->get()
        );

        $rows = [];
        foreach (AiCitationSourceRegistry::sources() as $key => $source) {
            $rows[$source['category']][] = [
                'key' => $key,
                'enabled' => ! in_array($key, $disabled, true),
                'channels' => $coverage[$source['cn_platform_key'] ?? ''] ?? [],
            ] + $source;
        }
        foreach ($rows as &$group) {
            usort($group, static fn (array $a, array $b): int => [$a['tier'], $a['key']] <=> [$b['tier'], $b['key']]);
        }
        unset($group);

        return view('admin.distribution.ai-sources', [
            'pageTitle' => __('admin_pages.ai_citation_sources'),
            'activeMenu' => 'distribution',
            'adminSiteName' => AdminWeb::siteName(),
            'aiTools' => AiCitationSourceRegistry::aiTools(),
            'groups' => $rows,
            'categoryLabels' => [
                AiCitationSourceRegistry::CATEGORY_QA => __('admin.distribution.ai_sources.category_qa'),
                AiCitationSourceRegistry::CATEGORY_NEWS => __('admin.distribution.ai_sources.category_news'),
                AiCitationSourceRegistry::CATEGORY_TECH => __('admin.distribution.ai_sources.category_tech'),
                AiCitationSourceRegistry::CATEGORY_LIFESTYLE => __('admin.distribution.ai_sources.category_lifestyle'),
                AiCitationSourceRegistry::CATEGORY_VERTICAL => __('admin.distribution.ai_sources.category_vertical'),
            ],
            'stats' => [
                'total' => count(AiCitationSourceRegistry::keys()),
                'enabled' => count(AiCitationSourceRegistry::keys()) - count($disabled),
                'covered' => count(array_filter(
                    AiCitationSourceRegistry::sources(),
                    static fn (array $source): bool => $source['publishable']
                        && ($coverage[$source['cn_platform_key'] ?? ''] ?? []) !== []
                )),
                'publishable' => count(array_filter(
                    AiCitationSourceRegistry::sources(),
                    static fn (array $source): bool => $source['publishable']
                )),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'enabled_sources' => ['nullable', 'array'],
            'enabled_sources.*' => ['string', Rule::in(AiCitationSourceRegistry::keys())],
        ]);

        $enabled = array_values(array_unique((array) ($payload['enabled_sources'] ?? [])));
        AiCitationSourceRegistry::setDisabledKeys(
            array_values(array_diff(AiCitationSourceRegistry::keys(), $enabled))
        );

        return redirect()
            ->route('admin.distribution.ai-sources.index')
            ->with('message', __('admin.distribution.ai_sources.message.updated'));
    }
}
