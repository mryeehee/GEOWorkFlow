<?php

namespace Tests\Feature;

use App\Models\BrandProfile;
use App\Services\GeoFlow\ArticleContentPromptRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleGeoPromptStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_prompt_carries_geo_structure_instruction(): void
    {
        $prompt = $this->render('GEO 是什么', 'GEO优化');

        $this->assertStringContainsString('【GEO引用结构指令】', $prompt);
        $this->assertStringContainsString('常见问题', $prompt);
        $this->assertStringNotContainsString('品牌信息：', $prompt);
    }

    public function test_geo_instruction_embeds_brand_profile(): void
    {
        BrandProfile::query()->create([
            'brand_name' => 'GEOWorkFlow',
            'brand_keywords' => ['GEO优化', 'AI 收录'],
            'business_scope' => '提供 GEO 内容诊断与分发服务',
        ]);

        $prompt = $this->render('GEO 是什么', 'GEO优化');

        $this->assertStringContainsString('官方名称「GEOWorkFlow」', $prompt);
        $this->assertStringContainsString('核心关键词：GEO优化、AI 收录', $prompt);
        $this->assertStringContainsString('业务范围：提供 GEO 内容诊断与分发服务', $prompt);
    }

    public function test_english_prompt_gets_english_geo_instruction(): void
    {
        $prompt = $this->render(
            'What is GEO',
            'generative engine optimization',
            'Please write a detailed English article about generative engine optimization and how brands get cited by AI assistants like ChatGPT and Perplexity today.'
        );

        $this->assertStringContainsString('[GEO Citation Structure]', $prompt);
        $this->assertStringNotContainsString('【GEO引用结构指令】', $prompt);
    }

    public function test_custom_prompt_keeping_the_marker_is_not_duplicated(): void
    {
        $prompt = $this->render(
            'GEO 是什么',
            'GEO优化',
            '【GEO引用结构指令】按我自己的规范写作。'
        );

        $this->assertSame(1, substr_count($prompt, '【GEO引用结构指令】'));
    }

    private function render(string $title, string $keyword, ?string $promptContent = null): string
    {
        return app(ArticleContentPromptRenderer::class)->renderForWorker($title, $keyword, $promptContent);
    }
}
