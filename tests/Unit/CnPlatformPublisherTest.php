<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleDistribution;
use App\Models\Author;
use App\Models\Category;
use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Services\GeoFlow\CnPlatformPublisher;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CnPlatformPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_weibo_oauth_publish_posts_share_and_maps_remote_id(): void
    {
        Http::fake([
            'https://api.weibo.com/2/statuses/share.json' => Http::response([
                'id' => 123456789,
                'idstr' => '123456789',
            ], 200),
        ]);

        [$channel, $distribution] = $this->makeDistribution('weibo', 'oauth2', [], [
            'access_token' => 'wb-token',
            'uid' => '42',
        ]);

        $result = app(CnPlatformPublisher::class)->publish($distribution, [
            'event' => 'article.publish',
            'article' => [
                'title' => '微博测试标题',
                'slug' => 'weibo-test',
                'excerpt' => '微博测试摘要',
                'content' => '正文',
            ],
        ]);

        $this->assertSame('123456789', $result['remote_id']);
        $this->assertSame('https://m.weibo.cn/detail/123456789', $result['remote_url']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.weibo.com/2/statuses/share.json'
                && str_contains((string) ($request['status'] ?? ''), '微博测试标题')
                && (string) ($request['access_token'] ?? '') === 'wb-token';
        });

        $channel->load('activeSecret');
        $this->assertNotNull($channel->activeSecret?->last_used_at);
    }

    public function test_weibo_oauth_health_requires_uid(): void
    {
        Http::fake();

        [$channel] = $this->makeDistribution('weibo', 'oauth2', [], ['access_token' => 'wb-token']);

        $result = app(CnPlatformPublisher::class)->health($channel);

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_uid', $result['error']);
        Http::assertNothingSent();
    }

    public function test_api_channel_publishes_json_with_bearer_header(): void
    {
        Http::fake([
            'https://gateway.example.com/publish' => Http::response([
                'data' => ['id' => 'r-1', 'url' => 'https://blog.example/r-1'],
            ], 201),
        ]);

        [, $distribution] = $this->makeDistribution('csdn', 'api', [
            'cn_api_publish_url' => 'https://gateway.example.com/publish',
            'cn_api_remote_id_path' => 'data.id',
            'cn_api_remote_url_path' => 'data.url',
        ], ['access_token' => 'api-token']);

        $result = app(CnPlatformPublisher::class)->publish($distribution, [
            'event' => 'article.publish',
            'article' => [
                'title' => 'API 渠道文章',
                'slug' => 'api-channel',
                'content' => '正文内容',
                'content_html' => '<p>正文内容</p>',
            ],
        ]);

        $this->assertSame('r-1', $result['remote_id']);
        $this->assertSame('https://blog.example/r-1', $result['remote_url']);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://gateway.example.com/publish'
                && $request->hasHeader('Authorization', 'Bearer api-token')
                && $request['title'] === 'API 渠道文章';
        });
    }

    public function test_api_channel_query_token_appends_access_token(): void
    {
        Http::fake([
            'https://gateway.example.com/pub*' => Http::response(['id' => 'r-2'], 200),
        ]);

        [, $distribution] = $this->makeDistribution('toutiao', 'api', [
            'cn_api_publish_url' => 'https://gateway.example.com/pub',
            'cn_api_auth_style' => 'query_token',
        ], ['access_token' => 'qt-token']);

        $result = app(CnPlatformPublisher::class)->publish($distribution, [
            'article' => ['title' => 'Query Token', 'slug' => 'qt', 'content' => 'x'],
        ]);

        $this->assertSame('r-2', $result['remote_id']);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_contains($request->url(), 'access_token=qt-token'));
    }

    public function test_browser_assist_channel_is_rejected_from_auto_queue(): void
    {
        [, $distribution] = $this->makeDistribution('zhihu', 'browser_assist');

        try {
            app(CnPlatformPublisher::class)->publish($distribution, ['article' => []]);
            $this->fail('浏览器辅助渠道不应进入自动分发。');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('手动发布中心', $exception->getMessage());
        }
    }

    public function test_browser_assist_health_is_ok_without_secret(): void
    {
        Http::fake();

        [$channel] = $this->makeDistribution('zhihu', 'browser_assist');

        $result = app(CnPlatformPublisher::class)->health($channel);

        $this->assertTrue($result['ok']);
        $this->assertSame('browser_assist', $result['mode']);
        $this->assertNotSame('', (string) $result['write_url']);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string,mixed>  $configOverrides
     * @param  array<string,mixed>  $secretPayload
     * @return array{0:DistributionChannel,1:ArticleDistribution}
     */
    private function makeDistribution(string $platform, string $mode, array $configOverrides = [], array $secretPayload = []): array
    {
        $channel = DistributionChannel::query()->create([
            'name' => '国内平台 '.$platform,
            'domain' => $platform.'.com',
            'endpoint_url' => 'https://'.$platform.'.com',
            'channel_type' => DistributionChannel::TYPE_CN_PLATFORM,
            'channel_config' => array_merge([
                'cn_platform' => $platform,
                'cn_auth_mode' => $mode,
            ], $configOverrides),
            'status' => 'active',
        ]);

        if ($secretPayload !== []) {
            DistributionChannelSecret::query()->create([
                'distribution_channel_id' => (int) $channel->id,
                'key_id' => 'cn_test',
                'secret_ciphertext' => app(ApiKeyCrypto::class)->encrypt(json_encode($secretPayload)),
                'status' => 'active',
                'scopes' => ['cn.platform'],
            ]);
        }

        $category = Category::query()->create(['name' => '科技', 'slug' => 'tech-cn']);
        $author = Author::query()->create(['name' => 'GEOWorkFlow']);
        $article = Article::query()->create([
            'title' => '渠道测试文章',
            'slug' => 'cn-channel-test',
            'content' => '正文',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);

        $distribution = ArticleDistribution::query()->create([
            'article_id' => (int) $article->id,
            'distribution_channel_id' => (int) $channel->id,
            'action' => 'publish',
            'status' => 'queued',
            'idempotency_key' => 'cn-test-'.$platform.'-'.$mode,
        ]);

        return [$channel, $distribution];
    }
}
