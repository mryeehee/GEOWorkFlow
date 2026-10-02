<?php

namespace Tests\Feature;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Data\Ai\SystemAiIdentity;
use App\Models\Admin;
use App\Models\AiModel;
use App\Models\AiSourceProvider;
use App\Models\KeywordOpportunity;
use App\Models\SiteSetting;
use App\Services\GeoFlow\KeywordMiningService;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class GeoFlowKeywordMiningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createProvider(int $dailyLimit = 20): AiSourceProvider
    {
        return AiSourceProvider::query()->create([
            'name' => 'Doubao Search Custom',
            'provider_key' => AiSourceProvider::PROVIDER_DOUBAO_SEARCH_CUSTOM,
            'endpoint_url' => 'https://open.feedcoopapi.com/search_api/web_search',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('search-key'),
            'status' => 'active',
            'daily_limit' => $dailyLimit,
        ]);
    }

    private function bindAnalysisModel(): void
    {
        $owner = Admin::query()->create([
            'username' => 'mining_owner',
            'password' => 'secret-123',
            'email' => 'mining-owner@example.com',
            'display_name' => 'Mining Owner',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $model = new AiModel;
        $model->forceFill([
            'owner_admin_id' => $owner->id,
            'access_scope' => AiModel::ACCESS_SCOPE_SYSTEM_ONLY,
            'name' => 'DeepSeek Mining',
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
    }

    private function fakeSearchResponses(array $titles): void
    {
        $results = [];
        foreach ($titles as $index => $title) {
            $results[] = [
                'Title' => $title,
                'Url' => 'https://zhihu.com/p/'.(1000 + $index),
                'Snippet' => $title.' 的摘要内容',
            ];
        }

        Http::fake([
            'https://open.feedcoopapi.com/search_api/web_search' => Http::response([
                'LogId' => 'log_mining',
                'Result' => ['WebResults' => $results],
            ]),
        ]);
    }

    public function test_mine_requires_enabled_search_provider(): void
    {
        Http::preventStrayRequests();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('关键词挖掘需要启用中的检索信源');

        app(KeywordMiningService::class)->mine(SystemAiIdentity::forVisibilityCollection(), 'GEO优化');
    }

    public function test_mine_persists_scored_opportunities_from_analysis(): void
    {
        Queue::fake();
        $this->createProvider();
        $this->bindAnalysisModel();
        $this->fakeSearchResponses([
            'GEO优化入门教程 - 知乎',
            'GEO优化公司怎么选？5个标准',
            'AI收录优化实操指南',
            'GEO优化入门教程 - 百家号',
        ]);
        MarkdownContentWriterAgent::fake([
            '[{"keyword":"GEO优化入门教程","score":88,"intent":"教程学习","angle":"教程式"},{"keyword":"AI收录优化实操指南","score":60,"intent":"实操","angle":"问答式"}]',
        ])->preventStrayPrompts();

        $saved = app(KeywordMiningService::class)->mine(SystemAiIdentity::forVisibilityCollection(), 'GEO优化', 12);

        $this->assertNotEmpty($saved);
        $keywords = array_map(static fn (KeywordOpportunity $row): string => (string) $row->keyword, $saved);
        $this->assertContains('GEO优化入门教程', $keywords);
        $this->assertContains('AI收录优化实操指南', $keywords);
        // 分析结果之外的候选词被过滤
        $this->assertNotContains('GEO优化公司怎么选？5个标准', $keywords);

        $top = KeywordOpportunity::query()->where('keyword', 'GEO优化入门教程')->first();
        $this->assertNotNull($top);
        $this->assertSame(88, (int) $top->score);
        $this->assertSame(KeywordOpportunity::STATUS_NEW, (string) $top->status);
        $this->assertSame('教程式', (string) $top->analysis_json['angle']);
        // 同题证据（两次检索均命中）保留 top3
        $this->assertNotEmpty((array) $top->evidence_json);
    }

    public function test_mine_keeps_existing_status_and_raises_score_on_remine(): void
    {
        Queue::fake();
        KeywordOpportunity::query()->create([
            'brand_keyword' => 'GEO优化',
            'keyword' => 'GEO优化入门教程',
            'score' => 50,
            'status' => KeywordOpportunity::STATUS_DISMISSED,
        ]);

        $this->createProvider();
        $this->fakeSearchResponses(['GEO优化入门教程 - 知乎']);
        // 无分析模型绑定：按频次打分 frequency*15

        app(KeywordMiningService::class)->mine(SystemAiIdentity::forVisibilityCollection(), 'GEO优化', 12);

        $row = KeywordOpportunity::query()->where('keyword', 'GEO优化入门教程')->first();
        $this->assertSame(KeywordOpportunity::STATUS_DISMISSED, (string) $row->status);
        $this->assertGreaterThanOrEqual(50, (int) $row->score);
    }
}
