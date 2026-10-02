<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordLibrary;
use App\Models\KeywordOpportunity;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class KeywordOpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, [KeywordOpportunity::STATUS_NEW, KeywordOpportunity::STATUS_IMPORTED, KeywordOpportunity::STATUS_DISMISSED], true)) {
            $status = '';
        }

        $opportunities = KeywordOpportunity::query()
            ->when($status !== '', static fn ($query) => $query->where('status', $status))
            ->orderByDesc('score')
            ->orderByDesc('last_mined_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.keyword-opportunities.index', [
            'pageTitle' => __('admin_pages.keyword_opportunities'),
            'activeMenu' => 'materials',
            'adminSiteName' => AdminWeb::siteName(),
            'opportunities' => $opportunities,
            'status' => $status,
            'libraries' => KeywordLibrary::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => KeywordOpportunity::query()->count(),
                'new' => KeywordOpportunity::query()->where('status', KeywordOpportunity::STATUS_NEW)->count(),
                'imported' => KeywordOpportunity::query()->where('status', KeywordOpportunity::STATUS_IMPORTED)->count(),
            ],
        ]);
    }

    public function mine(): RedirectResponse
    {
        try {
            Artisan::queue('geoflow:mine-keyword-opportunities');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['mine' => __('admin.keyword_opportunities.error.mine_failed')]);
        }

        return back()->with('message', __('admin.keyword_opportunities.message.mine_queued'));
    }

    public function import(Request $request, int $opportunityId): RedirectResponse
    {
        $payload = $request->validate([
            'library_id' => ['nullable', 'integer', Rule::exists('keyword_libraries', 'id')],
        ]);

        $opportunity = KeywordOpportunity::query()->whereKey($opportunityId)->firstOrFail();
        if ((string) $opportunity->status !== KeywordOpportunity::STATUS_NEW) {
            return back()->withErrors(['import' => __('admin.keyword_opportunities.error.already_handled')]);
        }

        $keywordLibrary = DB::transaction(function () use ($payload, $opportunity): Keyword {
            $library = isset($payload['library_id']) && (int) $payload['library_id'] > 0
                ? KeywordLibrary::query()->whereKey((int) $payload['library_id'])->firstOrFail()
                : KeywordLibrary::query()->firstOrCreate(
                    ['name' => __('admin.keyword_opportunities.auto_library_name')],
                    ['description' => __('admin.keyword_opportunities.auto_library_desc'), 'keyword_count' => 0]
                );

            KeywordLibrary::query()->whereKey($library->id)->lockForUpdate()->firstOrFail();
            $keyword = Keyword::query()->createOrFirst([
                'library_id' => $library->id,
                'keyword' => (string) $opportunity->keyword,
            ], [
                'used_count' => 0,
                'usage_count' => 0,
            ]);
            if ($keyword->wasRecentlyCreated) {
                KeywordLibrary::query()->whereKey($library->id)->increment('keyword_count');
            }

            $opportunity->fill([
                'status' => KeywordOpportunity::STATUS_IMPORTED,
                'imported_keyword_id' => (int) $keyword->id,
                'imported_at' => now(),
            ]);
            $opportunity->save();

            return $keyword;
        }, 3);

        return back()->with('message', __('admin.keyword_opportunities.message.imported', [
            'keyword' => (string) $opportunity->keyword,
        ]));
    }

    public function dismiss(int $opportunityId): RedirectResponse
    {
        $opportunity = KeywordOpportunity::query()->whereKey($opportunityId)->firstOrFail();
        if ((string) $opportunity->status === KeywordOpportunity::STATUS_IMPORTED) {
            return back()->withErrors(['dismiss' => __('admin.keyword_opportunities.error.already_handled')]);
        }

        $opportunity->fill([
            'status' => KeywordOpportunity::STATUS_DISMISSED,
            'dismissed_at' => now(),
        ]);
        $opportunity->save();

        return back()->with('message', __('admin.keyword_opportunities.message.dismissed'));
    }
}
