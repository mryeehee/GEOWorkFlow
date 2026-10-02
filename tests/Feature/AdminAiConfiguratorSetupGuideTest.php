<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AiModel;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiConfiguratorSetupGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_renders_required_setup_guide_with_key_portals(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai.configurator'))
            ->assertOk()
            ->assertSee('data-ai-configurator-setup-guide', false)
            ->assertSee(__('admin.ai_configurator.setup_guide_title'))
            ->assertSee(__('admin.ai_configurator.status_pending'))
            ->assertSee(__('admin.ai_key_guides.title'))
            ->assertSee('https://platform.openai.com/api-keys', false)
            ->assertSee(route('admin.ai-models.create'), false);

        $html = (string) $this->actingAs($admin, 'admin')
            ->get(route('admin.ai.configurator'))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'data-ai-configurator-modules'),
            strpos($html, 'data-ai-configurator-setup-guide'),
            'The setup guide should render before the management module cards.'
        );
    }

    public function test_checklist_flips_to_ready_once_a_chat_model_is_configured(): void
    {
        $admin = $this->admin();
        $this->model($admin);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai.configurator'))
            ->assertOk()
            ->assertSee(__('admin.ai_configurator.status_ready'));
    }

    public function test_model_create_page_lists_api_key_portal_for_every_preset(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ai-models.create'))
            ->assertOk()
            ->assertSee('data-api-key-guide', false)
            ->assertSee('https://platform.deepseek.com/api_keys', false)
            ->assertSee('data-key-apply-guide', false)
            ->assertSee(__('admin.ai_key_guides.title'));
    }

    public function test_source_provider_create_page_lists_volcengine_and_deepseek_portals(): void
    {
        $admin = $this->admin();

        $this->withSession([Admin::AUTH_VERSION_SESSION_KEY => 1])
            ->actingAs($admin, 'admin')
            ->get(route('admin.ai-source-providers.create'))
            ->assertOk()
            ->assertSee('data-api-key-guide', false)
            ->assertSee('https://console.volcengine.com/ark', false)
            ->assertDontSee('https://platform.openai.com/api-keys', false);
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'setup_guide_admin',
            'password' => 'secret-123',
            'email' => 'setup-guide-admin@example.com',
            'display_name' => 'Setup Guide Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function model(Admin $owner): AiModel
    {
        $model = new AiModel;

        $model->forceFill([
            'owner_admin_id' => $owner->id,
            'access_scope' => AiModel::ACCESS_SCOPE_SYSTEM_ONLY,
            'name' => 'Guide Chat Model',
            'version' => 'v1',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('guide-secret'),
            'model_id' => 'deepseek-chat',
            'model_type' => 'chat',
            'api_url' => 'https://api.deepseek.com',
            'failover_priority' => 100,
            'daily_limit' => 0,
            'used_today' => 0,
            'total_used' => 0,
            'status' => 'active',
        ])->save();

        return $model;
    }
}
