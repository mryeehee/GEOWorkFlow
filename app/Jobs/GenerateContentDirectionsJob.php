<?php

namespace App\Jobs;

use App\Data\Ai\SystemAiIdentity;
use App\Services\GeoFlow\ContentDirectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateContentDirectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public ?int $adminId = null) {}

    public function handle(ContentDirectionService $service): void
    {
        $service->generate(SystemAiIdentity::forVisibilityCollection(), $this->adminId);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
