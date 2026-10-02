<?php

namespace App\Console\Commands;

use App\Data\Ai\SystemAiIdentity;
use App\Models\ContentDirection;
use App\Services\GeoFlow\ContentDirectionService;
use Illuminate\Console\Command;
use Throwable;

class GeoFlowGenerateContentDirectionsCommand extends Command
{
    protected $signature = 'geoflow:content-directions:generate';

    protected $description = 'Generate GEO content direction suggestions from brand profile, hot keywords and visibility gaps';

    public function handle(ContentDirectionService $service): int
    {
        try {
            $identity = SystemAiIdentity::forVisibilityCollection();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('System AI identity is not available for content direction generation.');

            return self::FAILURE;
        }

        $direction = $service->generate($identity);

        if ((string) $direction->status !== ContentDirection::STATUS_COMPLETED) {
            $this->error(sprintf('Content direction generation failed: %s', (string) $direction->error_message));

            return self::FAILURE;
        }

        $this->info(sprintf('Content directions generated: %d suggestions', count((array) $direction->suggestions_json)));

        return self::SUCCESS;
    }
}
