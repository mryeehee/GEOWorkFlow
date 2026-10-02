<?php

namespace App\Services\GeoFlow;

use App\Models\Article;
use App\Models\ArticleDistribution;
use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Services\Outbound\SafeOutboundHttpClient;
use App\Services\Outbound\SafeOutboundRequest;
use App\Services\Site\SiteUrlGenerator;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\CnPlatformRegistry;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CnPlatformPublisher implements DistributionPublisherInterface
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly SafeOutboundHttpClient $safeHttp,
    ) {}

    public function health(DistributionChannel $channel): array
    {
        $config = $channel->resolvedCnPlatformConfig();
        $platform = (string) $config['cn_platform'];
        $mode = (string) $config['cn_auth_mode'];

        if ($mode === CnPlatformRegistry::AUTH_BROWSER_ASSIST) {
            return [
                'ok' => true,
                'channel_type' => 'cn_platform',
                'platform' => $platform,
                'mode' => $mode,
                'note' => '浏览器辅助渠道经手动发布中心与配套扩展发布，不直接请求平台接口。',
                'write_url' => $config['cn_write_url'],
            ];
        }

        $secret = $this->secretPayload($channel);
        $token = (string) ($secret['access_token'] ?? $secret['api_token'] ?? $secret['token'] ?? '');
        if ($token === '') {
            return [
                'ok' => false,
                'channel_type' => 'cn_platform',
                'platform' => $platform,
                'mode' => $mode,
                'error' => 'missing_secret',
                'note' => '渠道尚未保存有效的平台凭据（token/access_token）。',
            ];
        }

        if ($mode === CnPlatformRegistry::AUTH_OAUTH2) {
            $uid = (string) ($secret['uid'] ?? '');
            if ($uid === '') {
                return [
                    'ok' => false,
                    'channel_type' => 'cn_platform',
                    'platform' => $platform,
                    'mode' => $mode,
                    'error' => 'missing_uid',
                    'note' => '请先通过微博扫码授权完成连接，以写入账号 uid。',
                ];
            }
            $base = $config['cn_api_base'] !== '' ? $config['cn_api_base'] : 'https://api.weibo.com';
            $result = $this->send($this->baseRequest(15), 'GET', $base.'/2/users/show.json', ['uid' => $uid, 'access_token' => $token]);

            return [
                'ok' => $result['status_code'] >= 200 && $result['status_code'] < 300,
                'channel_type' => 'cn_platform',
                'platform' => $platform,
                'mode' => $mode,
                'status_code' => $result['status_code'],
                'account' => (string) data_get($result['json'], 'screen_name', ''),
            ];
        }

        $probeUrl = $config['cn_api_publish_url'] !== '' ? $config['cn_api_publish_url'] : $config['cn_api_base'];
        if ($probeUrl === '') {
            return [
                'ok' => false,
                'channel_type' => 'cn_platform',
                'platform' => $platform,
                'mode' => $mode,
                'error' => 'missing_publish_url',
                'note' => '请先填写平台发布接口地址。',
            ];
        }
        $result = $this->send($this->baseRequest(15), 'GET', $this->withQueryToken($config, $token, $probeUrl)['url'], []);

        return [
            'ok' => $result['status_code'] >= 200 && $result['status_code'] < 500,
            'channel_type' => 'cn_platform',
            'platform' => $platform,
            'mode' => $mode,
            'status_code' => $result['status_code'],
            'endpoint' => $probeUrl,
        ];
    }

    public function publish(ArticleDistribution $distribution, array $payload): array
    {
        $distribution->loadMissing(['channel', 'article']);
        [$channel, $config] = $this->channelConfig($distribution);

        return match ((string) $config['cn_auth_mode']) {
            CnPlatformRegistry::AUTH_OAUTH2 => $this->publishWeibo($distribution, $payload, $channel, $config),
            CnPlatformRegistry::AUTH_API => $this->publishApi($distribution, $payload, $channel, $config, $config['cn_api_publish_url'], 'publish'),
            default => throw new RuntimeException('国内平台浏览器辅助渠道请通过手动发布中心发布，不走自动分发队列。'),
        };
    }

    public function update(ArticleDistribution $distribution, array $payload): array
    {
        $distribution->loadMissing(['channel', 'article']);
        [$channel, $config] = $this->channelConfig($distribution);
        $mode = (string) $config['cn_auth_mode'];

        if ($mode === CnPlatformRegistry::AUTH_OAUTH2) {
            $distribution->loadMissing('channel');
            $secret = $this->secretPayload($channel);
            $token = (string) ($secret['access_token'] ?? '');
            if ($token === '') {
                throw new RuntimeException('微博渠道缺少 access_token。');
            }
            // 微博动态不可编辑：删除旧动态后按新内容重发，保持远端与站内一致。
            $this->deleteWeiboStatus($channel, $config, $token, (string) ($distribution->remote_id ?? ''));

            return $this->publishWeibo($distribution, $payload, $channel, $config);
        }

        if ($mode === CnPlatformRegistry::AUTH_API) {
            $url = $config['cn_api_update_url'] !== ''
                ? $config['cn_api_update_url']
                : $config['cn_api_publish_url'];
            $url = str_replace('{remote_id}', rawurlencode((string) ($distribution->remote_id ?? '')), $url);

            return $this->publishApi($distribution, $payload, $channel, $config, $url, 'update');
        }

        throw new RuntimeException('国内平台浏览器辅助渠道不支持远程更新。');
    }

    public function delete(ArticleDistribution $distribution): array
    {
        $distribution->loadMissing('channel');
        [$channel, $config] = $this->channelConfig($distribution);
        $mode = (string) $config['cn_auth_mode'];

        if ($mode === CnPlatformRegistry::AUTH_OAUTH2) {
            $secret = $this->secretPayload($channel);
            $token = (string) ($secret['access_token'] ?? '');
            if ($token === '') {
                throw new RuntimeException('微博渠道缺少 access_token。');
            }
            $this->deleteWeiboStatus($channel, $config, $token, (string) ($distribution->remote_id ?? ''));

            return [
                'deleted' => true,
                'remote_id' => (string) ($distribution->remote_id ?? ''),
                'remote_url' => null,
                'remote_meta' => ['cn_platform' => ['platform' => $config['cn_platform'], 'mode' => $mode, 'action' => 'delete']],
            ];
        }

        if ($mode === CnPlatformRegistry::AUTH_API && $config['cn_api_update_url'] !== '') {
            // 未提供删除接口的 API 渠道保持人工处理，不误报删除成功。
            throw new RuntimeException('该国内平台 API 渠道未提供删除能力，请在平台后台手动处理。');
        }

        throw new RuntimeException('该国内平台渠道不支持远程删除，请在平台后台手动处理。');
    }

    public function syncSiteSettings(DistributionChannel $channel, ?string $idempotencyKey = null, ?array $settings = null): array
    {
        return [
            'ok' => true,
            'skipped' => true,
            'reason' => 'cn_platform_no_site_settings_sync',
        ];
    }

    /**
     * @return array{0:DistributionChannel,1:array<string,mixed>}
     */
    private function channelConfig(ArticleDistribution $distribution): array
    {
        $channel = $distribution->channel;
        if (! $channel instanceof DistributionChannel) {
            throw new RuntimeException('分发记录缺少国内平台渠道。');
        }

        return [$channel, $channel->resolvedCnPlatformConfig()];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    private function publishWeibo(ArticleDistribution $distribution, array $payload, DistributionChannel $channel, array $config): array
    {
        $secret = $this->secretPayload($channel);
        $token = (string) ($secret['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('微博渠道缺少 access_token，请重新完成扫码授权。');
        }

        $base = $config['cn_api_base'] !== '' ? $config['cn_api_base'] : 'https://api.weibo.com';
        $title = mb_substr(trim((string) data_get($payload, 'article.title', '')), 0, (int) $config['cn_title_limit']);
        $summary = mb_substr(trim((string) data_get($payload, 'article.excerpt', '')), 0, (int) $config['cn_summary_limit']);
        $sourceUrl = $this->sourceUrl($distribution, $payload);

        $text = $title;
        if ($summary !== '' && $summary !== $title) {
            $text .= "\n\n".$summary;
        }
        if ($config['cn_append_source_link'] && $sourceUrl !== '') {
            $text .= "\n".$sourceUrl;
        }
        if (trim($text) === '') {
            throw new RuntimeException('微博发布内容为空。');
        }

        $result = $this->send(
            $this->baseRequest(20)->asJson(),
            'POST',
            $base.'/2/statuses/share.json',
            ['access_token' => $token, 'status' => $text],
        );
        $this->guardWeiboResponse($result, '微博发布');
        $this->markSecretUsed($channel);

        $id = (string) (data_get($result['json'], 'idstr') ?: data_get($result['json'], 'id', ''));
        $url = (string) data_get($result['json'], 'url', '');
        if ($url === '' && $id !== '') {
            $url = 'https://m.weibo.cn/detail/'.$id;
        }

        return [
            'remote_id' => $id !== '' ? $id : (string) ($distribution->remote_id ?? ''),
            'remote_url' => $url !== '' ? $url : (string) ($distribution->remote_url ?? ''),
            'remote_meta' => [
                'cn_platform' => [
                    'platform' => 'weibo',
                    'mode' => CnPlatformRegistry::AUTH_OAUTH2,
                    'status_code' => $result['status_code'],
                    'endpoint' => $base.'/2/statuses/share.json',
                    'text_length' => mb_strlen($text),
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $config
     * @return array<string,mixed>
     */
    private function publishApi(
        ArticleDistribution $distribution,
        array $payload,
        DistributionChannel $channel,
        array $config,
        string $url,
        string $operation,
    ): array {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('国内平台 API 渠道缺少有效的发布接口地址。');
        }

        $secret = $this->secretPayload($channel);
        $token = (string) ($secret['api_token'] ?? $secret['access_token'] ?? $secret['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('国内平台 API 渠道缺少凭据 token。');
        }

        $content = match ((string) $config['cn_content_format']) {
            'text' => trim((string) preg_replace('/\s+/', ' ', strip_tags((string) data_get($payload, 'article.content_html', data_get($payload, 'article.content', ''))))),
            'markdown' => (string) data_get($payload, 'article.content', ''),
            default => (string) data_get($payload, 'article.content_html', data_get($payload, 'article.content', '')),
        };

        $body = [
            (string) $config['cn_api_title_field'] => mb_substr(trim((string) data_get($payload, 'article.title', '')), 0, (int) $config['cn_title_limit']),
            (string) $config['cn_api_content_field'] => $content,
            (string) $config['cn_api_url_field'] => $this->sourceUrl($distribution, $payload),
            'format' => (string) $config['cn_content_format'],
            'status' => (string) $config['cn_post_status'],
            'article' => is_array($payload['article'] ?? null) ? $payload['article'] : [],
        ];
        if (! $config['cn_append_source_link']) {
            unset($body[(string) $config['cn_api_url_field']]);
        }

        $auth = $this->withQueryToken($config, $token, $url);
        $request = $this->baseRequest(30)->asJson();
        if ($config['cn_api_auth_style'] === 'header_bearer') {
            $request = $request->withToken($token);
        } elseif ($config['cn_api_auth_style'] === 'header_key') {
            $request = $request->withHeader((string) $config['cn_api_auth_header'], $token);
        }

        $result = $this->send($request, (string) $config['cn_api_method'], $auth['url'], array_merge($body, $auth['query']));
        if ($result['status_code'] < 200 || $result['status_code'] >= 300) {
            throw new RuntimeException('国内平台文章'.$operation.'失败：HTTP '.$result['status_code']);
        }
        $this->markSecretUsed($channel);

        return [
            'remote_id' => (string) (data_get($result['json'], (string) $config['cn_api_remote_id_path'], '') ?: ($distribution->remote_id ?? '')),
            'remote_url' => (string) (data_get($result['json'], (string) $config['cn_api_remote_url_path'], '') ?: ($distribution->remote_url ?? '')),
            'remote_meta' => [
                'cn_platform' => [
                    'platform' => $config['cn_platform'],
                    'mode' => CnPlatformRegistry::AUTH_API,
                    'status_code' => $result['status_code'],
                    'endpoint' => $auth['url'],
                    'operation' => $operation,
                ],
            ],
        ];
    }

    private function deleteWeiboStatus(DistributionChannel $channel, array $config, string $token, string $remoteId): void
    {
        if ($remoteId === '') {
            return;
        }
        $base = $config['cn_api_base'] !== '' ? $config['cn_api_base'] : 'https://api.weibo.com';
        $result = $this->send(
            $this->baseRequest(20)->asJson(),
            'POST',
            $base.'/2/statuses/destroy.json',
            ['access_token' => $token, 'id' => $remoteId],
        );
        if ($result['status_code'] >= 500) {
            throw new RuntimeException('微博动态删除失败：HTTP '.$result['status_code']);
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function sourceUrl(ArticleDistribution $distribution, array $payload): string
    {
        $article = $distribution->article;
        if ($article instanceof Article) {
            try {
                return (string) app(SiteUrlGenerator::class)->article($article);
            } catch (\Throwable) {
                // 站点路由不可用时回落到站内 slug 链接。
            }
        }

        $slug = trim((string) data_get($payload, 'article.slug', ''));

        return $slug !== '' ? rtrim((string) config('app.url'), '/').'/'.$slug : '';
    }

    /**
     * @return array{url:string,query:array<string,string>}
     */
    private function withQueryToken(array $config, string $token, ?string $url = null): array
    {
        $url = (string) ($url ?? $config['cn_api_publish_url'] ?? $config['cn_api_update_url'] ?? '');
        if ($url === '') {
            return ['url' => '', 'query' => []];
        }

        if ((string) ($config['cn_api_auth_style'] ?? '') !== 'query_token' || $token === '') {
            return ['url' => $url, 'query' => []];
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return ['url' => $url.$separator.'access_token='.rawurlencode($token), 'query' => []];
    }

    /**
     * @return array<string,mixed>
     */
    private function secretPayload(DistributionChannel $channel): array
    {
        $channel->loadMissing('activeSecret');
        $secret = $channel->activeSecret;
        if (! $secret instanceof DistributionChannelSecret) {
            return [];
        }

        $plain = $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext);
        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : ['token' => $plain];
    }

    private function baseRequest(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)
            ->connectTimeout(5)
            ->acceptJson();
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{status_code:int,json:array<string,mixed>}
     */
    private function send(PendingRequest $request, string $method, string $url, array $data): array
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('国内平台接口地址无效：'.$url);
        }

        $safe = new SafeOutboundRequest(
            $this->safeHttp,
            $request,
            (int) config('geoflow.outbound_json_max_bytes', 4 * 1024 * 1024),
        );

        $response = match (strtoupper($method)) {
            'GET' => $safe->get($url, $data),
            'DELETE' => $safe->delete($url, $data),
            default => $safe->post($url, $data),
        };

        return [
            'status_code' => $response->status(),
            'json' => $this->json($response),
        ];
    }

    /**
     * @param  array{status_code:int,json:array<string,mixed>}  $result
     */
    private function guardWeiboResponse(array $result, string $operationLabel): void
    {
        $status = (int) $result['status_code'];
        $errorCode = data_get($result['json'], 'error_code');
        if ($status >= 200 && $status < 300 && ! is_scalar($errorCode)) {
            return;
        }
        if ($errorCode !== null) {
            throw new RuntimeException($operationLabel.'失败：微博返回错误 '.$errorCode.'（'.(string) data_get($result['json'], 'error_description', '').'）');
        }

        throw new RuntimeException($operationLabel.'失败：HTTP '.$status);
    }

    /**
     * @return array<string,mixed>
     */
    private function json(Response $response): array
    {
        if ($response->status() === 204 || trim((string) $response->body()) === '') {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function markSecretUsed(DistributionChannel $channel): void
    {
        $channel->loadMissing('activeSecret');
        if ($channel->activeSecret) {
            $channel->activeSecret->forceFill(['last_used_at' => now()])->save();
        }
    }
}
