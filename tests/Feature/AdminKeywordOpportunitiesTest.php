<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\KeywordLibrary;
use App\Models\KeywordOpportunity;
use App\Support\AdminWeb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminKeywordOpportunitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geoflow.admin_ui_v3_enabled', true);
    }

    private ?Admin $admin = null;

    private function admin(): Admin
    {
        if ($this->admin !== null) {
            return $this->admin;
        }

        return $this->admin = Admin::query()->create([
            'username' => 'keyword_opportunities_admin',
            'password' => 'secret-123',
            'email' => 'keyword-opportunities-admin@example.com',
            'display_name' => 'Keyword Opportunities Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function opportunity(array $attributes = []): KeywordOpportunity
    {
        return KeywordOpportunity::query()->create(array_merge([
            'brand_keyword' => 'GEO优化',
            'keyword' => 'AI 引用优化实践',
            'score' => 82,
            'source_domain' => 'zhihu.com',
            'status' => KeywordOpportunity::STATUS_NEW,
            'analysis_json' => ['score' => 82, 'intent' => '实操指南', 'angle' => '对比测评'],
            'last_mined_at' => now(),
        ], $attributes));
    }

    public function test_index_page_renders_with_shell_and_opportunity(): void
    {
        $this->opportunity();

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.keyword-opportunities.index'));

        $response->assertOk()
            ->assertSee('data-gf-shell', false)
            ->assertSee('AI 引用优化实践')
            ->assertSee('zhihu.com');
    }

    public function test_index_filters_by_status(): void
    {
        $this->opportunity(['keyword' => '新词A']);
        $this->opportunity(['keyword' => '已入库词B', 'status' => KeywordOpportunity::STATUS_IMPORTED]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.keyword-opportunities.index', ['status' => 'imported']))
            ->assertOk()
            ->assertSee('已入库词B')
            ->assertDontSee('新词A');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.keyword-opportunities.index', ['status' => 'bogus']))
            ->assertOk()
            ->assertSee('新词A')
            ->assertSee('已入库词B');
    }

    public function test_mine_queues_command(): void
    {
        Artisan::shouldReceive('queue')
            ->once()
            ->with('geoflow:mine-keyword-opportunities')
            ->andReturn(0);

        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.keyword-opportunities.index'))
            ->post(route('admin.keyword-opportunities.mine'))
            ->assertRedirect(route('admin.keyword-opportunities.index'))
            ->assertSessionHas('message');
    }

    public function test_import_creates_keyword_in_chosen_library(): void
    {
        $library = KeywordLibrary::query()->create([
            'name' => '既有词库',
            'description' => '',
            'keyword_count' => 0,
        ]);
        $opportunity = $this->opportunity(['keyword' => '导入测试词']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.keyword-opportunities.import', ['opportunityId' => $opportunity->id]), [
                'library_id' => $library->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('message');

        $this->assertDatabaseHas('keywords', [
            'library_id' => $library->id,
            'keyword' => '导入测试词',
        ]);
        $this->assertSame(1, (int) $library->fresh()->keyword_count);
        $this->assertDatabaseHas('keyword_opportunities', [
            'id' => $opportunity->id,
            'status' => KeywordOpportunity::STATUS_IMPORTED,
        ]);
    }

    public function test_import_without_library_creates_auto_library(): void
    {
        $opportunity = $this->opportunity(['keyword' => '自动库词']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.keyword-opportunities.import', ['opportunityId' => $opportunity->id]))
            ->assertRedirect()
            ->assertSessionHas('message');

        $library = KeywordLibrary::query()->where('name', __('admin.keyword_opportunities.auto_library_name'))->first();
        $this->assertNotNull($library);
        $this->assertDatabaseHas('keywords', [
            'library_id' => $library->id,
            'keyword' => '自动库词',
        ]);
    }

    public function test_import_rejects_handled_opportunity(): void
    {
        $opportunity = $this->opportunity(['status' => KeywordOpportunity::STATUS_DISMISSED]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.keyword-opportunities.import', ['opportunityId' => $opportunity->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('import');

        $this->assertDatabaseHas('keyword_opportunities', [
            'id' => $opportunity->id,
            'status' => KeywordOpportunity::STATUS_DISMISSED,
        ]);
    }

    public function test_dismiss_marks_opportunity_and_protects_imported(): void
    {
        $opportunity = $this->opportunity(['keyword' => '忽略测试词']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.keyword-opportunities.dismiss', ['opportunityId' => $opportunity->id]))
            ->assertRedirect()
            ->assertSessionHas('message');

        $this->assertDatabaseHas('keyword_opportunities', [
            'id' => $opportunity->id,
            'status' => KeywordOpportunity::STATUS_DISMISSED,
        ]);

        $imported = $this->opportunity(['keyword' => '已导入词', 'status' => KeywordOpportunity::STATUS_IMPORTED]);
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.keyword-opportunities.dismiss', ['opportunityId' => $imported->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('dismiss');

        $this->assertSame(KeywordOpportunity::STATUS_IMPORTED, $imported->fresh()->status);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.keyword-opportunities.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_page_identity_localized_in_all_supported_locales(): void
    {
        $locales = array_keys(AdminWeb::supportedLocales());

        foreach ($locales as $locale) {
            $this->app->setLocale($locale);
            $label = __('admin_pages.keyword_opportunities');

            $this->assertNotSame('admin_pages.keyword_opportunities', $label, $locale);
            $this->assertNotSame('', $label, $locale);
        }
    }
}
