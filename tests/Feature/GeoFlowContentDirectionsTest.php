<?php

namespace Tests\Feature;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Jobs\GenerateContentDirectionsJob;
use App\Models\Admin;
use App\Models\AiModel;
use App\Models\BrandProfile;
use App\Models\ContentDirection;
use App\Models\KeywordOpportunity;
use App\Models\SiteSetting;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GeoFlowContentDirectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geoflow.admin_ui_v3_enabled', true);
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'content_directions_admin',
            'password' => 'secret-123',
            'email' => 'content-directions-admin@example.com',
            'display_name' => 'Content Directions Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function bindAnalysisModel(): void
    {
        $owner = $this->admin();
        $model = new AiModel;
        $model->forceFill([
            'owner_admin_id' => $owner->id,
            'access_scope' => AiModel::ACCESS_SCOPE_SYSTEM_ONLY,
            'name' => 'DeepSeek Directions',
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

    private function brandFixture(): void
    {
        BrandProfile::query()->create([
            'brand_name' => 'GEOWorkFlow',
            'brand_keywords' => ['GEO优化'],
            'official_domains' => ['example.com'],
        ]);
        KeywordOpportunity::query()->create([
            'brand_keyword' => 'GEO优化',
            'keyword' => 'AI 引用优化实践',
            'score' => 90,
            'status' => KeywordOpportunity::STATUS_NEW,
        ]);
    }

    public function test_command_generates_completed_directions_with_parsed_suggestions(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        MarkdownContentWriterAgent::fake([
            '[{"topic":"GEO优化入门指南","keyword":"GEO优化","angle":"教程式","target_sources":["知乎"],"priority":2,"reason":"品牌引用为0"},{"topic":"2026 AI 搜索信源榜单","keyword":"AI 引用优化实践","angle":"榜单式","target_sources":[],"priority":1,"reason":"热门词热度90"}]',
        ])->preventStrayPrompts();

        $this->bindAnalysisModel();
        $this->brandFixture();

        $this->artisan('geoflow:content-directions:generate')
            ->expectsOutputToContain('Content directions generated')
            ->assertSuccessful();

        $direction = ContentDirection::query()->latest('id')->first();
        $this->assertNotNull($direction);
        $this->assertSame(ContentDirection::STATUS_COMPLETED, (string) $direction->status);

        $suggestions = (array) $direction->suggestions_json;
        $this->assertCount(2, $suggestions);
        $this->assertSame('2026 AI 搜索信源榜单', $suggestions[0]['topic']);
        $this->assertSame(1, $suggestions[0]['priority']);
        $this->assertNotNull($direction->generated_at);
    }

    public function test_command_fails_when_answer_is_not_parsable(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        MarkdownContentWriterAgent::fake(['抱歉，我无法输出 JSON。'])->preventStrayPrompts();

        $this->bindAnalysisModel();
        $this->brandFixture();

        $this->artisan('geoflow:content-directions:generate')->assertFailed();

        $direction = ContentDirection::query()->latest('id')->first();
        $this->assertNotNull($direction);
        $this->assertSame(ContentDirection::STATUS_FAILED, (string) $direction->status);
        $this->assertNotSame('', (string) $direction->error_message);
    }

    public function test_command_fails_without_analysis_model_binding(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        $this->brandFixture();

        $this->artisan('geoflow:content-directions:generate')->assertFailed();

        $direction = ContentDirection::query()->latest('id')->first();
        $this->assertSame(ContentDirection::STATUS_FAILED, (string) $direction->status);
    }

    public function test_index_page_renders_shell_with_latest_suggestions(): void
    {
        ContentDirection::query()->create([
            'status' => ContentDirection::STATUS_COMPLETED,
            'suggestions_json' => [[
                'topic' => 'GEO优化入门指南',
                'keyword' => 'GEO优化',
                'angle' => '教程式',
                'target_sources' => ['知乎'],
                'priority' => 1,
                'reason' => '品牌引用缺口',
            ]],
            'inputs_json' => ['brand_name' => 'GEOWorkFlow'],
            'generated_at' => now(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.analytics.content-directions.index'))
            ->assertOk()
            ->assertSee('data-gf-shell', false)
            ->assertSee('GEO优化入门指南')
            ->assertSee('教程式');
    }

    public function test_generate_route_dispatches_job(): void
    {
        Queue::fake();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.analytics.content-directions.generate'))
            ->assertRedirect()
            ->assertSessionHas('message');

        Queue::assertPushed(GenerateContentDirectionsJob::class);
    }

    public function test_page_identity_localized_in_all_supported_locales(): void
    {
        foreach (array_keys(AdminWeb::supportedLocales()) as $locale) {
            $this->app->setLocale($locale);

            $label = __('admin_pages.content_directions');

            $this->assertNotSame('admin_pages.content_directions', $label, $locale);
            $this->assertNotSame('', $label, $locale);
        }
    }
}
