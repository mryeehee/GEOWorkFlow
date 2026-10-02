<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Services\GeoFlow\DistributionOrchestrator;
use App\Services\Outbound\SafeOutboundHttpClient;
use App\Services\Outbound\SafeOutboundRequest;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\CnPlatformRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CnPlatformOauthController extends Controller
{
    private const STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly SafeOutboundHttpClient $safeHttp,
        private readonly DistributionOrchestrator $distributionOrchestrator,
    ) {}

    public function start(Request $request, int $channelId): RedirectResponse|View
    {
        $channel = DistributionChannel::query()->whereKey($channelId)->first();
        if (! $channel) {
            return redirect()->route('admin.distribution.index')->withErrors(__('admin.distribution.message.not_found'));
        }

        $viewData = [
            'pageTitle' => __('admin.distribution.oauth.title'),
            'activeMenu' => 'distribution',
            'adminSiteName' => AdminWeb::siteName(),
            'channel' => $channel,
        ];

        if (! $channel->isCnPlatform() || (string) $channel->resolvedCnPlatformConfig()['cn_auth_mode'] !== CnPlatformRegistry::AUTH_OAUTH2) {
            return view('admin.distribution.cn-oauth-invalid', array_merge($viewData, [
                'oauthError' => '该渠道不是国内平台 OAuth2 授权渠道，无法发起扫码授权。',
            ]));
        }

        $secret = $this->secretPayload($channel);
        $appKey = trim((string) ($secret['app_key'] ?? ''));
        $appSecret = trim((string) ($secret['app_secret'] ?? ''));
        if ($appKey === '' || $appSecret === '') {
            return view('admin.distribution.cn-oauth-invalid', array_merge($viewData, [
                'oauthError' => '请先在渠道设置中保存平台应用的 App Key 与 App Secret，再发起扫码授权。',
            ]));
        }

        $preset = CnPlatformRegistry::get((string) $channel->resolvedCnPlatformConfig()['cn_platform']) ?? [];
        $authorizeUrl = (string) ($preset['authorize_url'] ?? 'https://open.weibo.com/oauth2/authorize');
        $redirectUri = trim((string) ($secret['redirect_uri'] ?? ''))
            ?: route('admin.distribution.cn-oauth.callback', ['channelId' => (int) $channel->id]);

        $state = Str::random(40);
        Cache::put($this->stateCacheKey($state), [
            'channel_id' => (int) $channel->id,
            'redirect_uri' => $redirectUri,
        ], self::STATE_TTL_SECONDS);

        $query = [
            'client_id' => $appKey,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];
        $fullAuthorizeUrl = $authorizeUrl.(str_contains($authorizeUrl, '?') ? '&' : '?').http_build_query($query);

        return view('admin.distribution.cn-oauth-start', array_merge($viewData, [
            'authorizeUrl' => $fullAuthorizeUrl,
            'redirectUri' => $redirectUri,
        ]));
    }

    public function callback(Request $request, int $channelId): RedirectResponse
    {
        $state = trim((string) $request->query('state'));
        $code = trim((string) $request->query('code'));
        $cacheKey = $this->stateCacheKey($state);
        $context = $state !== '' ? Cache::get($cacheKey) : null;
        if (! is_array($context) || (int) ($context['channel_id'] ?? 0) !== $channelId) {
            return redirect()
                ->route('admin.distribution.show', ['channelId' => $channelId])
                ->withErrors('授权会话已过期或与渠道不匹配，请重新发起扫码授权。');
        }
        Cache::forget($cacheKey);

        $channel = DistributionChannel::query()->whereKey($channelId)->first();
        if (! $channel || ! $channel->isCnPlatform()) {
            return redirect()->route('admin.distribution.index')->withErrors(__('admin.distribution.message.not_found'));
        }

        if ($code === '' || $request->query('error') !== null) {
            return redirect()
                ->route('admin.distribution.show', ['channelId' => $channelId])
                ->withErrors('平台返回授权失败：'.$request->query('error_description', $request->query('error', '未收到授权码，请重新发起扫码授权。')));
        }

        $secret = $this->secretPayload($channel);
        $appKey = trim((string) ($secret['app_key'] ?? ''));
        $appSecret = trim((string) ($secret['app_secret'] ?? ''));
        if ($appKey === '' || $appSecret === '') {
            return redirect()
                ->route('admin.distribution.show', ['channelId' => $channelId])
                ->withErrors('渠道缺少 App Key/App Secret，无法换取授权令牌。');
        }

        $preset = CnPlatformRegistry::get((string) $channel->resolvedCnPlatformConfig()['cn_platform']) ?? [];
        $tokenUrl = (string) ($preset['token_url'] ?? 'https://open.weibo.com/oauth2/access_token');

        try {
            $safe = new SafeOutboundRequest(
                $this->safeHttp,
                Http::timeout(15)->connectTimeout(5)->asForm(),
                (int) config('geoflow.outbound_json_max_bytes', 4 * 1024 * 1024),
            );
            $response = $safe->post($tokenUrl, [
                'client_id' => $appKey,
                'client_secret' => $appSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => (string) ($context['redirect_uri'] ?? ''),
            ]);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.distribution.show', ['channelId' => $channelId])
                ->withErrors('授权令牌换取请求失败：'.$e->getMessage());
        }

        $json = is_array($response->json()) ? (array) $response->json() : [];
        $accessToken = trim((string) ($json['access_token'] ?? ''));
        $uid = trim((string) ($json['uid'] ?? ''));
        if ($response->status() < 200 || $response->status() >= 300 || $accessToken === '' || $uid === '') {
            return redirect()
                ->route('admin.distribution.show', ['channelId' => $channelId])
                ->withErrors('授权令牌换取失败：'.(string) ($json['error_description'] ?? $json['error'] ?? 'HTTP '.$response->status()));
        }

        DistributionChannelSecret::query()
            ->where('distribution_channel_id', (int) $channel->id)
            ->where('status', 'active')
            ->update(['status' => 'revoked']);

        DistributionChannelSecret::query()->create([
            'distribution_channel_id' => (int) $channel->id,
            'key_id' => 'cn_'.Str::lower(Str::random(18)),
            'secret_ciphertext' => $this->apiKeyCrypto->encrypt(json_encode([
                'access_token' => $accessToken,
                'uid' => $uid,
                'app_key' => $appKey,
                'app_secret' => $appSecret,
                'redirect_uri' => (string) ($context['redirect_uri'] ?? ''),
            ], JSON_UNESCAPED_UNICODE)),
            'status' => 'active',
            'scopes' => ['cn.platform'],
        ]);

        $this->distributionOrchestrator->log(
            'info',
            '国内平台 OAuth 扫码授权完成，已写入访问令牌（uid: '.$uid.'）。',
            (int) $channel->id,
            null,
            null,
            ['event' => 'distribution.cn_oauth_authorized', 'platform' => (string) $channel->resolvedCnPlatformConfig()['cn_platform']]
        );

        return redirect()
            ->route('admin.distribution.show', ['channelId' => $channelId])
            ->with('message', '扫码授权完成，渠道凭据已更新。可点击“测试连接”验证账号连通性。');
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

        try {
            $plain = $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext);
        } catch (Throwable) {
            return [];
        }
        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : ['access_token' => $plain];
    }

    private function stateCacheKey(string $state): string
    {
        return 'geoflow:cn_oauth_state:'.$state;
    }
}
