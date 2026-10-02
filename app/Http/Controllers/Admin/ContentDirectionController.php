<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateContentDirectionsJob;
use App\Models\Admin;
use App\Models\ContentDirection;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContentDirectionController extends Controller
{
    public function index(): View
    {
        $latest = ContentDirection::query()->latest('id')->first();

        return view('admin.content-directions.index', [
            'pageTitle' => __('admin_pages.content_directions'),
            'activeMenu' => 'analytics',
            'adminSiteName' => AdminWeb::siteName(),
            'latest' => $latest,
            'history' => ContentDirection::query()
                ->select(['id', 'status', 'suggestions_json', 'error_message', 'generated_at', 'created_at'])
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function generate(): RedirectResponse
    {
        $admin = auth('admin')->user();

        GenerateContentDirectionsJob::dispatch($admin instanceof Admin ? (int) $admin->id : null);

        return back()->with('message', __('admin.content_directions.message.queued'));
    }
}
