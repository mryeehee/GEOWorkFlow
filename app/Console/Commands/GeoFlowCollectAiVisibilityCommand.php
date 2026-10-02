<?php

namespace App\Console\Commands;

use App\Data\Ai\SystemAiIdentity;
use App\Jobs\DetectAiVisibilityCompetitorsJob;
use App\Models\BrandProfile;
use App\Services\GeoFlow\AiVisibility\AiVisibilityCollectionService;
use App\Support\Site\SiteSettingsBag;
use Illuminate\Console\Command;
use Throwable;

class GeoFlowCollectAiVisibilityCommand extends Command
{
    protected $signature = 'geoflow:ai-visibility:collect
                            {keywords?* : One or more keywords to collect}
                            {--from-brand : Use brand profile keywords when no keywords are given}
                            {--limit=30 : Maximum keywords to collect in one run}';

    protected $description = 'Collect AI visibility search and analysis runs with saved provider bindings';

    public function handle(AiVisibilityCollectionService $collection): int
    {
        $keywords = $this->resolveKeywords();
        if ($keywords === []) {
            $this->error('At least one keyword is required (pass keywords or configure the brand profile).');

            return self::FAILURE;
        }

        $failed = 0;
        $identity = SystemAiIdentity::forVisibilityCollection();
        foreach ($keywords as $keyword) {
            try {
                $runs = $collection->collect($identity, $keyword);
                foreach ($runs as $run) {
                    if (trim((string) $run->answer_text) !== '') {
                        DetectAiVisibilityCompetitorsJob::dispatch((int) $run->id);
                    }
                }
                $this->info(sprintf('AI visibility collected: keyword=%s, runs=%d', $keyword, count($runs)));
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $this->error(sprintf('AI visibility collection failed: keyword=%s', $keyword));
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function resolveKeywords(): array
    {
        $explicit = array_values(array_unique(array_filter(array_map(
            static fn (mixed $keyword): string => trim((string) $keyword),
            (array) $this->argument('keywords')
        ))));
        if ($explicit !== []) {
            return $explicit;
        }

        $brand = BrandProfile::current();
        $brandName = trim((string) $brand->brand_name);
        $keywords = array_merge(
            (array) ($brand->brand_keywords ?? []),
            $brandName !== '' ? [$brandName] : []
        );

        if ($keywords === []) {
            $keywords = preg_split('/[,，、\s]+/u', SiteSettingsBag::get('site_keywords')) ?: [];
        }

        $keywords = array_values(array_unique(array_filter(array_map(
            static fn ($keyword): string => trim((string) $keyword),
            $keywords
        ))));

        $limit = max(1, (int) $this->option('limit'));

        return array_slice($keywords, 0, $limit);
    }
}
