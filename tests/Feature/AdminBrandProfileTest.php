<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BrandProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminBrandProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geoflow.admin_ui_v3_enabled', true);
        Cache::flush();
    }

    public function test_admin_can_view_the_brand_profile_form(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.brand.index'));

        $response->assertOk();
        $response->assertSee('data-gf-shell', false);
        $response->assertSee(__('admin.brand.page_title'));
        $response->assertSee('data-settings-navigation', false);
        $this->assertSame(0, $response->viewData('assets')['published_articles']);
    }

    public function test_brand_profile_persists_normalized_list_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->put(route('admin.brand.update'), [
            'brand_name' => '  GEOWorkFlow  ',
            'brand_aliases' => "GEOWorkFlow 官方\ngeoflow，GEOWorkFlow 官方",
            'brand_keywords' => "GEO优化，AI 收录\nGEO优化; 生成引擎",
            'business_scope' => '提供 GEO 内容诊断与分发服务',
            'industries' => '营销科技',
            'official_domains' => "https://Example.com/geoflow\nwww.geoflow.cn",
        ])->assertRedirect(route('admin.brand.index'));

        $profile = BrandProfile::query()->sole();
        $this->assertSame('GEOWorkFlow', $profile->brand_name);
        $this->assertSame(['GEOWorkFlow 官方', 'geoflow'], $profile->brand_aliases);
        $this->assertSame(['GEO优化', 'AI 收录', '生成引擎'], $profile->brand_keywords);
        $this->assertSame('提供 GEO 内容诊断与分发服务', $profile->business_scope);
        $this->assertSame(['营销科技'], $profile->industries);
        $this->assertSame(['example.com/geoflow', 'www.geoflow.cn'], $profile->official_domains);

        $this->assertSame($profile->id, BrandProfile::current()->id);
    }

    public function test_update_is_a_single_row_upsert(): void
    {
        $admin = $this->admin();

        $payload = [
            'brand_name' => '第一版',
            'brand_keywords' => 'GEO',
            'brand_aliases' => '',
            'business_scope' => '',
            'industries' => '',
            'official_domains' => '',
        ];

        $this->actingAs($admin, 'admin')->put(route('admin.brand.update'), $payload);
        $this->actingAs($admin, 'admin')->put(route('admin.brand.update'), array_merge($payload, ['brand_name' => '第二版']));

        $this->assertSame(1, BrandProfile::query()->count());
        $this->assertSame('第二版', BrandProfile::current()->brand_name);
    }

    public function test_brand_name_and_keywords_are_required(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.brand.update'), [
                'brand_name' => '',
                'brand_keywords' => "  \n ",
            ])
            ->assertSessionHasErrors(['brand_name', 'brand_keywords']);

        $this->assertSame(0, BrandProfile::query()->count());
    }

    public function test_brand_copy_resolves_in_supported_locales(): void
    {
        foreach (['zh_CN', 'zh_TW', 'en', 'ja', 'es', 'ru', 'pt_BR'] as $locale) {
            app()->setLocale($locale);

            $this->assertNotSame('admin_pages.brand_profile', __('admin_pages.brand_profile'), $locale);
            $this->assertNotSame('admin.brand.page_title', __('admin.brand.page_title'), $locale);
            $this->assertNotSame('admin.brand.field.brand_keywords', __('admin.brand.field.brand_keywords'), $locale);
        }
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'brand_profile_admin',
            'password' => 'secret-123',
            'email' => 'brand-profile-admin@example.com',
            'display_name' => 'Brand Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
