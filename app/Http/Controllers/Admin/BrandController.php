<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Author;
use App\Models\BrandProfile;
use App\Models\Category;
use App\Models\DistributionChannel;
use App\Models\Keyword;
use App\Models\KnowledgeBase;
use App\Models\Title;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $profile = BrandProfile::current();

        return view('admin.brand.index', [
            'pageTitle' => __('admin.brand.page_title'),
            'activeMenu' => 'site_settings',
            'adminSiteName' => AdminWeb::siteName(),
            'profile' => $profile,
            'form' => $this->formData($profile),
            'assets' => $this->assetOverview(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'brand_name' => ['required', 'string', 'max:160'],
            'brand_aliases' => ['nullable', 'string', 'max:2000'],
            'brand_keywords' => ['required', 'string', 'max:4000'],
            'business_scope' => ['nullable', 'string', 'max:6000'],
            'industries' => ['nullable', 'string', 'max:2000'],
            'official_domains' => ['nullable', 'string', 'max:2000'],
        ], [
            'brand_name.required' => __('admin.brand.validation.brand_name_required'),
            'brand_keywords.required' => __('admin.brand.validation.brand_keywords_required'),
        ]);

        $profile = BrandProfile::current();
        $profile->fill([
            'brand_name' => trim($payload['brand_name']),
            'brand_aliases' => $this->parseList($payload['brand_aliases'] ?? ''),
            'brand_keywords' => $this->parseList($payload['brand_keywords']),
            'business_scope' => trim((string) ($payload['business_scope'] ?? '')),
            'industries' => $this->parseList($payload['industries'] ?? ''),
            'official_domains' => $this->parseDomainList($payload['official_domains'] ?? ''),
            'updated_by_admin_id' => $request->user('admin')?->getKey(),
        ]);
        $profile->save();

        return redirect()
            ->route('admin.brand.index')
            ->with('message', __('admin.brand.message.updated'));
    }

    /**
     * @return array<string, string>
     */
    private function formData(BrandProfile $profile): array
    {
        return [
            'brand_name' => (string) ($profile->brand_name ?? ''),
            'brand_aliases' => implode("\n", (array) ($profile->brand_aliases ?? [])),
            'brand_keywords' => implode("\n", (array) ($profile->brand_keywords ?? [])),
            'business_scope' => (string) ($profile->business_scope ?? ''),
            'industries' => implode("\n", (array) ($profile->industries ?? [])),
            'official_domains' => implode("\n", (array) ($profile->official_domains ?? [])),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function assetOverview(): array
    {
        return [
            'published_articles' => Article::query()->where('status', 'published')->count(),
            'articles_total' => Article::query()->count(),
            'knowledge_bases' => KnowledgeBase::query()->count(),
            'keywords' => Keyword::query()->count(),
            'titles' => Title::query()->count(),
            'authors' => Author::query()->count(),
            'categories' => Category::query()->count(),
            'distribution_channels' => DistributionChannel::query()->where('status', 'active')->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function parseList(?string $value): array
    {
        $lines = preg_split('/[\r\n,，;；]+/u', (string) $value) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines
        ), static fn (string $item): bool => $item !== '')));
    }

    /**
     * @return list<string>
     */
    private function parseDomainList(?string $value): array
    {
        return array_values(array_map(
            static fn (string $domain): string => mb_strtolower(preg_replace('/^https?:\/\//', '', $domain) ?? $domain),
            $this->parseList($value)
        ));
    }
}
