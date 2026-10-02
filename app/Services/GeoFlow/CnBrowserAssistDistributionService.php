<?php

namespace App\Services\GeoFlow;

use App\Models\Admin;
use App\Models\Article;
use App\Models\DistributionChannel;
use App\Models\ManualPublication;
use App\Models\ManualPublicationPersona;
use App\Services\Site\SiteUrlGenerator;
use App\Support\GeoFlow\CnPlatformRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 国内平台"浏览器辅助"渠道：站内文章发布后，自动在手动发布中心生成待发布草稿，
 * 由已扫码登录的配套浏览器扩展领取发布（复用 manual-publications 状态机与回执）。
 */
class CnBrowserAssistDistributionService
{
    public function __construct(
        private readonly ManualPublicationService $manualPublications,
    ) {}

    /**
     * @param  list<DistributionChannel>  $channels
     * @return array{created: array<int,int>, skipped: array<int,string>, failed: array<int,string>}
     */
    public function createForArticle(Article $article, array $channels): array
    {
        $created = [];
        $skipped = [];
        $failed = [];

        foreach ($channels as $channel) {
            try {
                $result = $this->createOne($article, $channel);
                if ($result === null) {
                    $skipped[(int) $channel->id] = 'duplicate_or_missing_identity';
                } else {
                    $created[(int) $channel->id] = (int) $result;
                }
            } catch (Throwable $exception) {
                $failed[(int) $channel->id] = $exception->getMessage();
                Log::warning('cn_browser_assist_create_failed', [
                    'article_id' => (int) $article->id,
                    'channel_id' => (int) $channel->id,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'failed' => $failed];
    }

    private function createOne(Article $article, DistributionChannel $channel): ?int
    {
        $config = $channel->resolvedCnPlatformConfig();
        if (! CnPlatformRegistry::isBrowserAssistMode((string) $config['cn_auth_mode'])) {
            return null;
        }

        $platform = (string) $this->manualPlatformFor((string) $config['cn_platform']);
        if ($platform === '') {
            return null;
        }

        $existing = ManualPublication::query()
            ->where('article_id', (int) $article->id)
            ->where('platform', $platform)
            ->whereIn('status', [
                ManualPublication::STATUS_DRAFT,
                ManualPublication::STATUS_READY,
                ManualPublication::STATUS_IN_PROGRESS,
                ManualPublication::STATUS_COMPLETED,
            ])
            ->exists();
        if ($existing) {
            return null;
        }

        $persona = $this->resolvePersona($config['cn_platform'], $channel);
        if (! $persona instanceof ManualPublicationPersona) {
            return null;
        }

        $creator = $this->resolveCreator($channel);
        if (! $creator instanceof Admin) {
            return null;
        }

        $title = mb_substr(trim((string) $article->title), 0, (int) $config['cn_title_limit']);
        $excerpt = mb_substr(trim((string) ($article->excerpt ?? '')), 0, (int) $config['cn_summary_limit']);
        $body = $title;
        if ($excerpt !== '' && $excerpt !== $title) {
            $body .= "\n\n".$excerpt;
        }
        if (! empty($config['cn_append_source_link'])) {
            try {
                $url = (string) app(SiteUrlGenerator::class)->article($article);
            } catch (Throwable) {
                $url = '';
            }
            if ($url !== '') {
                $body .= "\n\n原文：".$url;
            }
        }

        $publication = $this->manualPublications->create([
            'type' => ManualPublication::TYPE_POST,
            'platform' => $platform,
            'persona_id' => (int) $persona->getKey(),
            'account_id' => null,
            'article_id' => (int) $article->id,
            'target_url' => (string) $config['cn_write_url'],
            'target_context' => trim(((string) (CnPlatformRegistry::get((string) $config['cn_platform'])['name'] ?? '')).' [cn_channel:'.(int) $channel->id.']'),
            'content' => $body,
            'status' => ManualPublication::STATUS_DRAFT,
            'scheduled_at' => null,
        ], $creator);

        return (int) $publication->getKey();
    }

    private function manualPlatformFor(string $cnPlatform): string
    {
        return (string) (CnPlatformRegistry::get($cnPlatform)['manual_platform'] ?? '');
    }

    private function resolvePersona(string $cnPlatform, DistributionChannel $channel): ?ManualPublicationPersona
    {
        $configuredId = (int) (is_array($channel->channel_config) ? ($channel->channel_config['cn_mp_persona_id'] ?? 0) : 0);
        if ($configuredId > 0) {
            $persona = ManualPublicationPersona::query()
                ->whereKey($configuredId)
                ->where('is_active', true)
                ->first();
            if ($persona instanceof ManualPublicationPersona) {
                return $persona;
            }
        }

        $manualPlatform = $this->manualPlatformFor($cnPlatform);

        return ManualPublicationPersona::query()
            ->where('is_active', true)
            ->whereHas('accounts', fn ($query) => $query->where('platform', $manualPlatform)->where('is_active', true))
            ->orderBy('id')
            ->first()
            ?? ManualPublicationPersona::query()->where('is_active', true)->orderBy('id')->first();
    }

    private function resolveCreator(DistributionChannel $channel): ?Admin
    {
        $creator = Admin::query()
            ->whereKey((int) $channel->created_by_admin_id)
            ->where('status', 'active')
            ->first();

        return $creator instanceof Admin
            ? $creator
            : Admin::query()->where('status', 'active')->orderBy('id')->first();
    }
}
