<?php

namespace App\Console\Commands;

use App\Data\Ai\SystemAiIdentity;
use App\Models\BrandProfile;
use App\Models\KeywordOpportunity;
use App\Services\GeoFlow\KeywordMiningService;
use Illuminate\Console\Command;
use Throwable;

class GeoFlowMineKeywordOpportunitiesCommand extends Command
{
    protected $signature = 'geoflow:mine-keyword-opportunities
                            {--keyword=* : Additional brand keywords to mine}
                            {--limit=12 : Maximum opportunities saved per keyword}';

    protected $description = 'Mine trending AI-cited long-tail keywords for brand keywords and store them as content opportunities';

    public function handle(KeywordMiningService $mining): int
    {
        $keywords = $this->resolveKeywords();
        if ($keywords === []) {
            $this->error('No brand keywords configured. Fill the brand profile (后台-品牌资料) first.');

            return self::FAILURE;
        }

        $identity = SystemAiIdentity::forVisibilityCollection();
        $limit = max(1, (int) $this->option('limit'));
        $failed = 0;
        $total = 0;

        foreach ($keywords as $keyword) {
            try {
                $saved = $mining->mine($identity, $keyword, $limit);
                $total += count($saved);
                $this->info(sprintf('Keyword mining done: keyword=%s, opportunities=%d', $keyword, count($saved)));
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error(sprintf('Keyword mining failed: keyword=%s', $keyword));
            }
        }

        $this->info(sprintf('Total opportunities stored: %d (new table rows: %d)', $total, KeywordOpportunity::query()->count()));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function resolveKeywords(): array
    {
        $brand = BrandProfile::current();
        $keywords = array_merge(
            (array) ($brand->brand_keywords ?? []),
            (array) ($brand->industries ?? []),
            array_map(strval(...), (array) $this->option('keyword'))
        );

        $brandName = trim((string) $brand->brand_name);
        if ($brandName !== '') {
            $keywords[] = $brandName;
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($keyword): string => trim((string) $keyword),
            $keywords
        ), static fn (string $keyword): bool => $keyword !== '')));
    }
}
