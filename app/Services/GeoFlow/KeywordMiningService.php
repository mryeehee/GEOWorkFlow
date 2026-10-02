<?php

namespace App\Services\GeoFlow;

use App\Data\Ai\SystemAiIdentity;
use App\Models\AiModel;
use App\Models\AiSourceProvider;
use App\Models\KeywordOpportunity;
use App\Services\GeoFlow\AiVisibility\AiVisibilityConfigurationResolver;
use App\Services\GeoFlow\AiVisibility\AiVisibilityService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * AI 引用热门关键词挖掘：借道可见性检索链抓取候选长尾词，再让分析模型深度打分，落库为选题机会。
 */
final class KeywordMiningService
{
    public function __construct(
        private readonly AiVisibilityConfigurationResolver $configuration,
        private readonly AiVisibilityService $visibility,
    ) {}

    /**
     * @return list<KeywordOpportunity>
     */
    public function mine(SystemAiIdentity $identity, string $brandKeyword, int $maxOpportunities = 12): array
    {
        $brandKeyword = trim($brandKeyword);
        if ($brandKeyword === '') {
            throw new RuntimeException('挖掘品牌关键词为空');
        }

        $provider = $this->configuration->searchProvider($identity);
        if (! $provider instanceof AiSourceProvider) {
            throw new RuntimeException('关键词挖掘需要启用中的检索信源（AI 信源渠道）');
        }

        $evidence = $this->collectEvidence($provider, $brandKeyword);
        if ($evidence === []) {
            return [];
        }

        $candidates = $this->rankCandidates($evidence, 40);
        $analysis = $this->analyze($identity, $brandKeyword, $candidates);

        return $this->persist($brandKeyword, $candidates, $evidence, $analysis, $maxOpportunities);
    }

    /**
     * @return list<string>
     */
    public function queryVariants(string $brandKeyword): array
    {
        return [
            $brandKeyword,
            $brandKeyword.' 推荐',
            $brandKeyword.' 怎么选',
            $brandKeyword.' 有哪些',
        ];
    }

    /**
     * @return list<array{keyword: string, title: string, url: string, domain: string, snippet: string}>
     */
    private function collectEvidence(AiSourceProvider $provider, string $brandKeyword): array
    {
        $evidence = [];

        foreach ($this->queryVariants($brandKeyword) as $query) {
            try {
                $run = $this->visibility->runDoubaoSearchCustom($provider, $query);
                $run->load('sources');
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            foreach ($run->sources as $source) {
                $candidate = $this->candidateFromTitle((string) ($source->title ?? ''));
                if ($candidate === '' || mb_strlen($candidate) < 4 || mb_strlen($candidate) > 40) {
                    continue;
                }

                $evidence[] = [
                    'keyword' => $candidate,
                    'title' => $candidate,
                    'url' => (string) ($source->url ?? ''),
                    'domain' => (string) ($source->domain ?? ''),
                    'snippet' => mb_substr(trim((string) ($source->snippet ?? $source->summary ?? '')), 0, 160),
                ];
            }
        }

        return $evidence;
    }

    private function candidateFromTitle(string $title): string
    {
        $clean = trim(preg_replace('/[\s]{2,}/u', ' ', $title) ?? $title);
        $parts = preg_split('/[|｜—–\-_·»»]+/u', $clean) ?: [];
        $main = trim((string) ($parts[0] ?? $clean));
        // trim($main, "...") would chop single bytes of multibyte punctuation and corrupt UTF-8.
        $main = preg_replace('/\A[\p{Z}\p{P}]+|[\p{Z}\p{P}]+\z/u', '', $main) ?? $main;

        return $main;
    }

    /**
     * @param  list<array<string, string>>  $evidence
     * @return list<array{keyword: string, frequency: int, domain: string}>
     */
    private function rankCandidates(array $evidence, int $limit): array
    {
        $counts = [];
        $domains = [];

        foreach ($evidence as $row) {
            $key = $row['keyword'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            if ($row['domain'] !== '' && ! isset($domains[$key])) {
                $domains[$key] = $row['domain'];
            }
        }
        arsort($counts);

        $ranked = [];
        foreach (array_slice($counts, 0, $limit, true) as $keyword => $frequency) {
            $ranked[] = [
                'keyword' => $keyword,
                'frequency' => (int) $frequency,
                'domain' => (string) ($domains[$keyword] ?? ''),
            ];
        }

        return $ranked;
    }

    /**
     * @param  list<array{keyword: string, frequency: int, domain: string}>  $candidates
     * @return array<string, array{score: int, intent: string, angle: string}>
     */
    private function analyze(SystemAiIdentity $identity, string $brandKeyword, array $candidates): array
    {
        $model = $this->configuration->deepSeekModel($identity);
        if (! $model instanceof AiModel) {
            return [];
        }

        try {
            $run = $this->visibility->runDeepSeekAnalysis(
                $identity,
                $model,
                $brandKeyword,
                $this->analysisPrompt($brandKeyword, $candidates),
            );

            return $this->parseAnalysis((string) ($run->answer_text ?? ''));
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  list<array{keyword: string, frequency: int, domain: string}>  $candidates
     */
    private function analysisPrompt(string $brandKeyword, array $candidates): string
    {
        $lines = [];
        foreach ($candidates as $index => $candidate) {
            $lines[] = sprintf('%d. %s（检索热度 %d）', $index + 1, $candidate['keyword'], $candidate['frequency']);
        }

        return "你是 GEO 关键词策略分析师。品牌核心关键词为「{$brandKeyword}」，下面是从 AI 高频引用信源检索结果中提取的候选长尾词。\n"
            ."请从中挑选最多 12 个最适合做 GEO 内容选题的关键词，按引用价值打分（0-100）并给出用户意图与内容切入角度。\n"
            ."只输出 JSON 数组，不要其他文字，格式：[{\"keyword\":\"...\",\"score\":85,\"intent\":\"...\",\"angle\":\"...\"}]\n"
            ."keyword 必须逐字来自候选列表。\n候选列表：\n".implode("\n", $lines);
    }

    /**
     * @return array<string, array{score: int, intent: string, angle: string}>
     */
    public function parseAnalysis(string $answer): array
    {
        $json = trim($answer);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $json) ?? $json;
        $start = mb_strpos($json, '[');
        $end = mb_strrpos($json, ']');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(mb_substr($json, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            return [];
        }

        $parsed = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $keyword = trim((string) ($row['keyword'] ?? ''));
            if ($keyword === '') {
                continue;
            }
            $parsed[$keyword] = [
                'score' => max(0, min(100, (int) ($row['score'] ?? 0))),
                'intent' => trim((string) ($row['intent'] ?? '')),
                'angle' => trim((string) ($row['angle'] ?? '')),
            ];
        }

        return $parsed;
    }

    /**
     * @param  list<array{keyword: string, frequency: int, domain: string}>  $candidates
     * @param  list<array<string, string>>  $evidence
     * @param  array<string, array{score: int, intent: string, angle: string}>  $analysis
     * @return list<KeywordOpportunity>
     */
    private function persist(string $brandKeyword, array $candidates, array $evidence, array $analysis, int $maxOpportunities): array
    {
        $scored = [];
        foreach ($candidates as $candidate) {
            $keyword = $candidate['keyword'];
            $analysed = $analysis[$keyword] ?? null;
            if ($analysis !== [] && $analysed === null) {
                continue;
            }

            $scored[] = [
                'keyword' => $keyword,
                'domain' => $candidate['domain'],
                'analysed' => $analysed,
                'score' => $analysed !== null
                    ? $analysed['score']
                    : max(1, min(100, $candidate['frequency'] * 15)),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, max(1, $maxOpportunities));

        $now = now();
        $saved = [];

        foreach ($scored as $row) {
            $saved[] = DB::transaction(function () use ($brandKeyword, $row, $evidence, $now): KeywordOpportunity {
                $opportunity = KeywordOpportunity::query()->firstOrNew([
                    'brand_keyword' => $brandKeyword,
                    'keyword' => $row['keyword'],
                ]);
                $opportunity->fill([
                    'score' => max((int) $opportunity->getAttribute('score'), $row['score']),
                    'source_domain' => $row['domain'] !== '' ? $row['domain'] : $opportunity->source_domain,
                    'analysis_json' => $row['analysed'],
                    'evidence_json' => array_slice(array_values(array_filter(
                        $evidence,
                        static fn (array $item): bool => $item['keyword'] === $row['keyword']
                    )), 0, 3),
                    'last_mined_at' => $now,
                ]);
                if (! $opportunity->exists) {
                    $opportunity->status = KeywordOpportunity::STATUS_NEW;
                }
                $opportunity->save();

                return $opportunity;
            });
        }

        return $saved;
    }
}
