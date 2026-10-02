<?php

namespace Tests\Feature;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\Admin;
use App\Models\AiModel;
use App\Models\AiSourceProvider;
use App\Models\AiVisibilityRun;
use App\Models\BrandProfile;
use App\Models\SiteSetting;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GeoFlowAiVisibilityCollectCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_collects_search_and_deepseek_analysis_with_saved_bindings(): void
    {
        $this->fakeVisibilityStack();
        $provider = $this->createVisibilityProvider();
        $model = $this->createAnalysisModel();

        $this->artisan('geoflow:ai-visibility:collect', [
            'keywords' => ['GEOWorkFlow'],
        ])
            ->expectsOutputToContain('GEOWorkFlow')
            ->assertSuccessful();

        $this->assertDatabaseHas('ai_visibility_runs', [
            'keyword' => 'GEOWorkFlow',
            'provider_type' => AiVisibilityRun::PROVIDER_DOUBAO_SEARCH_CUSTOM,
            'status' => AiVisibilityRun::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('ai_visibility_runs', [
            'keyword' => 'GEOWorkFlow',
            'provider_type' => AiVisibilityRun::PROVIDER_DEEPSEEK_ANALYSIS,
            'status' => AiVisibilityRun::STATUS_COMPLETED,
        ]);
        $this->assertSame(1, (int) $provider->fresh()->used_today);
        $this->assertSame(1, (int) $model->fresh()->used_today);
    }

    public function test_from_brand_collects_brand_profile_keywords(): void
    {
        // brand_keywords (2) + brand_name (1) = 3 keywords -> 3 analysis prompts
        $this->fakeVisibilityStack(4);
        $this->createVisibilityProvider();
        $this->createAnalysisModel();

        BrandProfile::query()->create([
            'brand_name' => 'GEOWorkFlow',
            'brand_keywords' => ['GEO优化', 'AI 收录'],
        ]);

        $this->artisan('geoflow:ai-visibility:collect', ['--from-brand' => true])
            ->expectsOutputToContain('GEO优化')
            ->expectsOutputToContain('AI 收录')
            ->assertSuccessful();

        $this->assertDatabaseHas('ai_visibility_runs', [
            'keyword' => 'GEO优化',
            'provider_type' => AiVisibilityRun::PROVIDER_DOUBAO_SEARCH_CUSTOM,
            'status' => AiVisibilityRun::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('ai_visibility_runs', [
            'keyword' => 'AI 收录',
            'provider_type' => AiVisibilityRun::PROVIDER_DOUBAO_SEARCH_CUSTOM,
            'status' => AiVisibilityRun::STATUS_COMPLETED,
        ]);
    }

    public function test_command_fails_closed_without_keywords_or_brand_profile(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        $this->artisan('geoflow:ai-visibility:collect', ['--from-brand' => true])
            ->expectsOutputToContain('At least one keyword is required')
            ->assertFailed();
    }

    private function fakeVisibilityStack(int $analysisResponses = 1): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://open.feedcoopapi.com/search_api/web_search' => Http::response([
                'LogId' => 'log_cli_collect',
                'Result' => [
                    'WebResults' => [
                        [
                            'Title' => 'GEOWorkFlow',
                            'Url' => 'https://example.com/geoflow',
                            'Snippet' => 'GEOWorkFlow visibility source',
                        ],
                    ],
                ],
            ]),
        ]);
        MarkdownContentWriterAgent::fake(array_fill(0, $analysisResponses, '分析完成'))->preventStrayPrompts();
    }

    private function createVisibilityProvider(): AiSourceProvider
    {
        return AiSourceProvider::query()->create([
            'name' => 'Doubao Search Custom',
            'provider_key' => AiSourceProvider::PROVIDER_DOUBAO_SEARCH_CUSTOM,
            'endpoint_url' => 'https://open.feedcoopapi.com/search_api/web_search',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('search-key'),
            'status' => 'active',
            'daily_limit' => 10,
        ]);
    }

    private function createAnalysisModel(): AiModel
    {
        $owner = Admin::query()->create([
            'username' => 'visibility_system_owner',
            'password' => 'secret-123',
            'email' => 'visibility-system-owner@example.com',
            'display_name' => 'Visibility System Owner',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $model = new AiModel;
        $model->forceFill([
            'owner_admin_id' => $owner->id,
            'access_scope' => AiModel::ACCESS_SCOPE_SYSTEM_ONLY,
            'name' => 'DeepSeek Analysis',
            'version' => 'test',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('deepseek-key'),
            'model_id' => 'deepseek-chat',
            'model_type' => 'chat',
            'api_url' => 'https://api.deepseek.com',
            'failover_priority' => 10,
            'daily_limit' => 10,
            'status' => 'active',
        ])->save();
        SiteSetting::query()->create([
            'setting_key' => 'ai_visibility_deepseek_analysis_model_id',
            'setting_value' => (string) $model->id,
        ]);

        return $model;
    }
}
