<?php

namespace App\Services\GeoFlow;

use App\Data\Ai\SystemAiIdentity;
use App\Models\AiVisibilityRun;
use App\Models\AiVisibilitySource;
use App\Models\BrandProfile;
use App\Models\ContentDirection;
use App\Models\DistributionChannel;
use App\Models\KeywordOpportunity;
use App\Services\GeoFlow\AiVisibility\AiVisibilityConfigurationResolver;
use App\Services\GeoFlow\AiVisibility\AiVisibilityService;
use App\Support\GeoFlow\AiCitationSourceRegistry;
use Illuminate\Support\Carbon;
use Throwable;

class ContentDirectionService
{
    public function __construct(
        private readonly AiVisibilityConfigurationResolver $resolver,
        private readonly AiVisibilityService $visibility,
    ) {}

    public function generate(SystemAiIdentity $identity, ?int $adminId = null): ContentDirection
    {
        $inputs = $this->collectInputs();

        $direction = ContentDirection::query()->create([
            'status' => ContentDirection::STATUS_GENERATING,
            'inputs_json' => $inputs,
            'created_by_admin_id' => $adminId,
        ]);

        $model = $this->resolver->deepSeekModel($identity);
        if ($model === null) {
            $direction->fill([
                'status' => ContentDirection::STATUS_FAILED,
                'error_message' => '未配置深度分析模型（AI 信源渠道-DeepSeek 分析模型）。',
            ]);
            $direction->save();

            return $direction;
        }

        try {
            $run = $this->visibility->runDeepSeekAnalysis(
                $identity,
                $model,
                (string) ($inputs['brand_keywords'][0] ?? ($inputs['brand_name'] !== '' ? $inputs['brand_name'] : '内容方向')),
                $this->buildPrompt($inputs),
            );

            $suggestions = $this->parseSuggestions((string) $run->answer_text);

            $direction->fill([
                'status' => $suggestions !== [] ? ContentDirection::STATUS_COMPLETED : ContentDirection::STATUS_FAILED,
                'suggestions_json' => $suggestions,
                'answer_text' => (string) $run->answer_text,
                'ai_visibility_run_id' => (int) $run->id,
                'generated_at' => now(),
                'error_message' => $suggestions !== [] ? null : '模型未返回可解析的选题方向 JSON。',
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $direction->fill([
                'status' => ContentDirection::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }

        $direction->save();

        return $direction;
    }

    /**
     * @return array<string, mixed>
     */
    public function collectInputs(): array
    {
        $brand = BrandProfile::current();
        $since = Carbon::now()->subDays(30);

        $opportunities = KeywordOpportunity::query()
            ->where('status', KeywordOpportunity::STATUS_NEW)
            ->orderByDesc('score')
            ->limit(15)
            ->get(['keyword', 'brand_keyword', 'score', 'analysis_json'])
            ->map(static fn (KeywordOpportunity $opportunity): array => [
                'keyword' => (string) $opportunity->keyword,
                'brand_keyword' => (string) $opportunity->brand_keyword,
                'score' => (int) $opportunity->score,
                'angle' => (string) ($opportunity->analysis_json['angle'] ?? ''),
            ])
            ->all();

        $brandKeywords = array_values(array_filter(array_map(
            static fn (mixed $keyword): string => trim((string) $keyword),
            (array) ($brand->brand_keywords ?? [])
        )));

        $visibilityGaps = $this->visibilityGaps($brandKeywords, $since);

        $channels = DistributionChannel::query()->where('channel_type', DistributionChannel::TYPE_CN_PLATFORM)->get();
        $covered = array_keys(AiCitationSourceRegistry::coverageFor($channels));
        $sourceGaps = [];
        foreach (AiCitationSourceRegistry::enabledSources() as $source) {
            if (! (bool) ($source['publishable'] ?? false)) {
                continue;
            }
            $platformKey = (string) ($source['cn_platform_key'] ?? '');
            if ($platformKey !== '' && ! in_array($platformKey, $covered, true)) {
                $sourceGaps[] = ['name' => (string) $source['name'], 'domain' => (string) $source['domain']];
            }
        }

        return [
            'brand_name' => trim((string) $brand->brand_name),
            'brand_keywords' => $brandKeywords,
            'industries' => array_values((array) ($brand->industries ?? [])),
            'business_scope' => mb_substr(trim((string) $brand->business_scope), 0, 300),
            'opportunities' => $opportunities,
            'visibility_gaps' => $visibilityGaps,
            'source_gaps' => array_slice($sourceGaps, 0, 8),
        ];
    }

    /**
     * 近 30 天按关键词统计：品牌官网/别名被引用比例，未被引用视为可见性缺口。
     *
     * @param  list<string>  $brandKeywords
     * @return list<array<string, mixed>>
     */
    private function visibilityGaps(array $brandKeywords, Carbon $since): array
    {
        $brand = BrandProfile::current();
        $ownedHosts = array_filter(array_map(
            static fn (mixed $domain): string => strtolower(trim((string) $domain)),
            (array) ($brand->official_domains ?? [])
        ));

        $keywords = array_slice($brandKeywords, 0, 10);
        if ($keywords === []) {
            return [];
        }

        $gaps = [];
        foreach ($keywords as $keyword) {
            $runIds = AiVisibilityRun::query()
                ->where('keyword', $keyword)
                ->where('status', AiVisibilityRun::STATUS_COMPLETED)
                ->where('created_at', '>=', $since)
                ->limit(20)
                ->pluck('id');
            if ($runIds === []) {
                continue;
            }

            $domains = AiVisibilitySource::query()
                ->whereIn('ai_visibility_run_id', $runIds)
                ->whereNotNull('domain')
                ->pluck('domain')
                ->map(static fn (string $domain): string => strtolower(trim($domain)))
                ->all();

            $counts = array_count_values($domains);
            arsort($counts);
            $topDomains = array_slice(array_keys($counts), 0, 5);
            $ownedCitations = 0;
            foreach ($domains as $domain) {
                foreach ($ownedHosts as $host) {
                    if ($host !== '' && str_contains($domain, $host)) {
                        $ownedCitations++;
                        break;
                    }
                }
            }

            $gaps[] = [
                'keyword' => $keyword,
                'runs' => count($runIds),
                'owned_citations' => $ownedCitations,
                'total_citations' => count($domains),
                'top_domains' => $topDomains,
            ];
        }

        usort($gaps, static fn (array $a, array $b): int => $a['owned_citations'] <=> $b['owned_citations']);

        return $gaps;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function buildPrompt(array $inputs): string
    {
        $lines = [];
        $lines[] = '你是一名 GEO（生成引擎优化）内容策略专家。请基于以下品牌与数据，输出最新的内容生成方向。';
        $lines[] = '品牌信息：'.json_encode([
            'brand_name' => $inputs['brand_name'],
            'brand_keywords' => $inputs['brand_keywords'],
            'industries' => $inputs['industries'],
            'business_scope' => $inputs['business_scope'],
        ], JSON_UNESCAPED_UNICODE);
        $lines[] = 'AI 引用热门关键词（含热度分与分析角度）：'.json_encode($inputs['opportunities'], JSON_UNESCAPED_UNICODE);
        $lines[] = '近30天品牌关键词被 AI 引用情况缺口（owned_citations 越低越急需内容覆盖）：'.json_encode($inputs['visibility_gaps'], JSON_UNESCAPED_UNICODE);
        $lines[] = '尚未建立发布渠道的高价值信源平台：'.json_encode($inputs['source_gaps'], JSON_UNESCAPED_UNICODE);
        $lines[] = '要求：输出 6-10 条选题方向，按优先级排序；每条包含 topic（选题标题方向）、keyword（主攻关键词）、angle（切入角度，如 问答式/榜单式/对比式/教程式）、target_sources（建议发布平台名数组，可为空）、reason（一句话依据，需引用上述数据）。';
        $lines[] = '只输出 JSON 数组，不要输出任何其他文字或代码围栏。格式：[{"topic":"","keyword":"","angle":"","target_sources":[],"priority":1,"reason":""}]';

        return implode("\n", $lines);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseSuggestions(string $answer): array
    {
        $answer = trim($answer);
        $answer = preg_replace('/^```[a-zA-Z]*\s*|```$/m', '', $answer) ?? $answer;

        $start = strpos($answer, '[');
        $end = strrpos($answer, ']');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($answer, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $topic = trim((string) ($row['topic'] ?? ''));
            if ($topic === '') {
                continue;
            }
            $rows[] = [
                'topic' => $topic,
                'keyword' => trim((string) ($row['keyword'] ?? '')),
                'angle' => trim((string) ($row['angle'] ?? '')),
                'target_sources' => array_values(array_filter(array_map(
                    static fn (mixed $source): string => trim((string) $source),
                    (array) ($row['target_sources'] ?? [])
                ))),
                'priority' => max(1, min(10, (int) ($row['priority'] ?? 10))),
                'reason' => trim((string) ($row['reason'] ?? '')),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return array_slice($rows, 0, 10);
    }
}
