<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DistributionChannel;
use App\Support\GeoFlow\AiCitationSourceRegistry;
use App\Support\GeoFlow\CnPlatformRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiCitationSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geoflow.admin_ui_v3_enabled', true);
    }

    public function test_registry_preset_is_well_formed(): void
    {
        $tools = AiCitationSourceRegistry::aiTools();
        $domains = [];

        foreach (AiCitationSourceRegistry::sources() as $key => $source) {
            $this->assertIsString($key);
            $this->assertNotSame('', $source['name']);
            $this->assertNotSame('', $source['domain']);
            $this->assertContains($source['category'], [
                AiCitationSourceRegistry::CATEGORY_QA,
                AiCitationSourceRegistry::CATEGORY_NEWS,
                AiCitationSourceRegistry::CATEGORY_TECH,
                AiCitationSourceRegistry::CATEGORY_LIFESTYLE,
                AiCitationSourceRegistry::CATEGORY_VERTICAL,
            ]);
            $this->assertGreaterThanOrEqual(1, $source['tier']);
            $this->assertLessThanOrEqual(3, $source['tier']);
            $this->assertNotEmpty($source['cited_by'], $key);

            foreach ($source['cited_by'] as $toolKey) {
                $this->assertArrayHasKey($toolKey, $tools, $key);
            }

            if ($source['publishable']) {
                $this->assertNotNull($source['cn_platform_key'], $key);
                $this->assertTrue(CnPlatformRegistry::exists($source['cn_platform_key']), $key);
            } else {
                $this->assertNull($source['cn_platform_key'], $key);
            }

            $domains[] = $source['domain'];
        }

        $this->assertSame(count($domains), count(array_unique($domains)));
    }

    public function test_admin_can_view_ai_citation_sources_page(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.distribution.ai-sources.index'))
            ->assertOk()
            ->assertSee('data-gf-shell', false)
            ->assertSee(__('admin_pages.ai_citation_sources'))
            ->assertSee('知乎')
            ->assertSee('豆包')
            ->assertSee('未建渠道')
            ->assertSee('间接信源');
    }

    public function test_page_shows_covered_channels_for_configured_platforms(): void
    {
        $admin = $this->admin();
        DistributionChannel::query()->create([
            'name' => '知乎主渠道',
            'domain' => 'zhihu.com',
            'endpoint_url' => 'https://zhihu.com',
            'channel_type' => 'cn_platform',
            'channel_config' => ['cn_platform' => 'zhihu', 'cn_auth_mode' => 'browser_assist'],
            'status' => 'active',
            'created_by_admin_id' => (int) $admin->id,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.distribution.ai-sources.index'));

        $response->assertOk()
            ->assertSee('知乎主渠道')
            ->assertSee('已覆盖', false)
            ->assertSee(route('admin.distribution.show', ['channelId' => 1], false));
    }

    public function test_uncovered_publishable_source_links_to_prefilled_channel_create(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.distribution.ai-sources.index'))
            ->assertOk()
            ->assertSee('cn_platform=sohu', false);

        $content = $this->actingAs($admin, 'admin')
            ->get(route('admin.distribution.create', ['channel_type' => 'cn_platform', 'cn_platform' => 'sohu']))
            ->assertOk()
            ->content();

        $this->assertMatchesRegularExpression('/value="sohu"[^>]*checked/', $content);
        $this->assertMatchesRegularExpression('/value="cn_platform"[^>]*checked/', $content);
    }

    public function test_enabled_selection_persist_across_requests(): void
    {
        $admin = $this->admin();
        $all = AiCitationSourceRegistry::keys();
        $keep = array_values(array_diff($all, ['csdn', 'weibo']));

        $this->actingAs($admin, 'admin')
            ->put(route('admin.distribution.ai-sources.update'), ['enabled_sources' => $keep])
            ->assertRedirect(route('admin.distribution.ai-sources.index'));

        $this->assertSame(['csdn', 'weibo'], AiCitationSourceRegistry::disabledKeys());

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.distribution.ai-sources.index'))
            ->assertOk();

        $content = $response->content();
        $this->assertMatchesRegularExpression('/value="csdn"(?![^>]*checked)/', $content);
        $this->assertMatchesRegularExpression('/value="weibo"(?![^>]*checked)/', $content);
        $this->assertMatchesRegularExpression('/value="zhihu"[^>]*checked/', $content);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.distribution.ai-sources.update'), ['enabled_sources' => $all])
            ->assertRedirect(route('admin.distribution.ai-sources.index'));

        $this->assertSame([], AiCitationSourceRegistry::disabledKeys());
    }

    public function test_update_rejects_unknown_source_keys(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.distribution.ai-sources.update'), ['enabled_sources' => ['zhihu', 'not-a-source']])
            ->assertSessionHasErrors('enabled_sources.1');

        $this->assertEmpty(AiCitationSourceRegistry::disabledKeys());
    }

    public function test_ai_sources_copy_resolves_in_supported_locales(): void
    {
        foreach (['zh_CN', 'zh_TW', 'en', 'ja', 'es', 'ru', 'pt_BR'] as $locale) {
            app()->setLocale($locale);

            $this->assertNotSame('admin_pages.ai_citation_sources', __('admin_pages.ai_citation_sources'), $locale);
        }

        app()->setLocale('zh_CN');
        $this->assertNotSame('admin.distribution.ai_sources.subtitle', __('admin.distribution.ai_sources.subtitle'));
        $this->assertNotSame('admin.distribution.ai_sources.create_channel', __('admin.distribution.ai_sources.create_channel'));
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'ai_sources_admin',
            'password' => 'secret-123',
            'email' => 'ai-sources-admin@example.com',
            'display_name' => 'AI Sources Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
