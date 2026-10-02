<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Models\ManualPublication;
use App\Models\ManualPublicationAccount;
use App\Models\ManualPublicationPersona;
use App\Models\Task;
use App\Services\GeoFlow\CnBrowserAssistDistributionService;
use App\Services\GeoFlow\DistributionOrchestrator;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminCnPlatformDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_offers_cn_platform_presets_and_mode_fields(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.distribution.create'))
            ->assertOk()
            ->assertSee(__('admin.distribution.channel_type.cn_platform'))
            ->assertSee(__('admin.distribution.cn.section_title'))
            ->assertSee('name="cn_platform" value="zhihu"', false)
            ->assertSee('name="cn_platform" value="xiaohongshu"', false)
            ->assertSee('name="cn_auth_mode" value="oauth2"', false)
            ->assertSee('name="cn_api_publish_url"', false)
            ->assertSee('name="cn_secret_app_key"', false);
    }

    public function test_admin_can_create_cn_platform_api_channel_with_encrypted_secret(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => 'CSDN 直连',
                'domain' => 'blog.csdn.net',
                'endpoint_url' => 'https://blog.csdn.net',
                'channel_type' => 'cn_platform',
                'cn_platform' => 'csdn',
                'cn_auth_mode' => 'api',
                'cn_api_publish_url' => 'https://gateway.example.com/publish',
                'cn_api_method' => 'POST',
                'cn_api_auth_style' => 'header_bearer',
                'cn_secret_token_api' => 'api-token',
                'cn_content_format' => 'html',
                'status' => 'active',
            ])
            ->assertSessionMissing('distribution_secret');

        $channel = DistributionChannel::query()->where('name', 'CSDN 直连')->firstOrFail();
        $this->assertSame('cn_platform', $channel->channelType());
        $config = $channel->resolvedCnPlatformConfig();
        $this->assertSame('csdn', $config['cn_platform']);
        $this->assertSame('api', $config['cn_auth_mode']);
        $this->assertSame('https://gateway.example.com/publish', $config['cn_api_publish_url']);

        $secret = DistributionChannelSecret::query()
            ->where('distribution_channel_id', (int) $channel->id)
            ->where('status', 'active')
            ->firstOrFail();
        $this->assertStringStartsWith('cn_', (string) $secret->key_id);
        $this->assertSame(['cn.platform'], $secret->scopes);
        $decoded = json_decode(app(ApiKeyCrypto::class)->decrypt((string) $secret->secret_ciphertext), true);
        $this->assertSame('api-token', $decoded['access_token']);
        $this->assertStringNotContainsString('api-token', (string) $secret->secret_ciphertext);
    }

    public function test_oauth2_channel_requires_credentials_on_create(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => '微博渠道',
                'domain' => 'weibo.com',
                'endpoint_url' => 'https://weibo.com',
                'channel_type' => 'cn_platform',
                'cn_platform' => 'weibo',
                'cn_auth_mode' => 'oauth2',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cn_secret_token');

        $this->assertDatabaseMissing('distribution_channels', ['name' => '微博渠道']);
    }

    public function test_unsupported_auth_mode_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => '知乎 API',
                'domain' => 'zhihu.com',
                'endpoint_url' => 'https://zhihu.com',
                'channel_type' => 'cn_platform',
                'cn_platform' => 'zhihu',
                'cn_auth_mode' => 'api',
                'cn_api_publish_url' => 'https://gateway.example.com/publish',
                'cn_secret_token_api' => 'x',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cn_auth_mode');
    }

    public function test_oauth_start_shows_callback_and_exchanges_code_for_secret(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => '微博扫码',
                'domain' => 'weibo.com',
                'endpoint_url' => 'https://weibo.com',
                'channel_type' => 'cn_platform',
                'cn_platform' => 'weibo',
                'cn_auth_mode' => 'oauth2',
                'cn_secret_app_key' => 'wb-app-key',
                'cn_secret_app_secret' => 'wb-app-secret',
                'status' => 'active',
            ])
            ->assertSessionDoesntHaveErrors();

        $channel = DistributionChannel::query()->where('name', '微博扫码')->firstOrFail();
        $callbackUrl = route('admin.distribution.cn-oauth.callback', ['channelId' => (int) $channel->id]);

        $start = $this->actingAs($admin, 'admin')
            ->get(route('admin.distribution.cn-oauth.start', ['channelId' => (int) $channel->id]))
            ->assertOk()
            ->assertSee(__('admin.distribution.oauth.title'))
            ->assertSee($callbackUrl, false);

        $this->assertSame(1, preg_match('/state=([A-Za-z0-9]{40})/', (string) $start->getContent(), $matches));

        Http::fake([
            'https://open.weibo.com/oauth2/access_token' => Http::response([
                'access_token' => 'wb-token',
                'uid' => '99001',
            ], 200),
        ]);

        $this->actingAs($admin, 'admin')
            ->get($callbackUrl.'?code=wb-code&state='.$matches[1])
            ->assertRedirect(route('admin.distribution.show', ['channelId' => (int) $channel->id]));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://open.weibo.com/oauth2/access_token'
            && (string) ($request['code'] ?? '') === 'wb-code'
            && (string) ($request['client_id'] ?? '') === 'wb-app-key'
            && (string) ($request['redirect_uri'] ?? '') === $callbackUrl);

        $secret = DistributionChannelSecret::query()
            ->where('distribution_channel_id', (int) $channel->id)
            ->where('status', 'active')
            ->sole();
        $decoded = json_decode(app(ApiKeyCrypto::class)->decrypt((string) $secret->secret_ciphertext), true);
        $this->assertSame('wb-token', $decoded['access_token']);
        $this->assertSame('99001', $decoded['uid']);
        $this->assertSame('wb-app-key', $decoded['app_key']);
        $this->assertSame($callbackUrl, $decoded['redirect_uri']);
        $this->assertSame(1, DistributionChannelSecret::query()->where('distribution_channel_id', (int) $channel->id)->where('status', 'revoked')->count());
    }

    public function test_oauth_callback_rejects_unknown_state(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => '微博扫码',
                'domain' => 'weibo.com',
                'endpoint_url' => 'https://weibo.com',
                'channel_type' => 'cn_platform',
                'cn_platform' => 'weibo',
                'cn_auth_mode' => 'oauth2',
                'cn_secret_app_key' => 'wb-app-key',
                'cn_secret_app_secret' => 'wb-app-secret',
                'status' => 'active',
            ])
            ->assertSessionDoesntHaveErrors();

        $channel = DistributionChannel::query()->where('name', '微博扫码')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.distribution.cn-oauth.callback', ['channelId' => (int) $channel->id]).'?code=x&state=bogus-state')
            ->assertRedirect(route('admin.distribution.show', ['channelId' => (int) $channel->id]))
            ->assertSessionHasErrors();

        Http::assertNothingSent();
    }

    public function test_browser_assist_channel_creates_manual_publication_draft_once(): void
    {
        $admin = $this->admin();

        $persona = ManualPublicationPersona::query()->create(['name' => 'GEOWorkFlow 专家']);
        ManualPublicationAccount::query()->create([
            'persona_id' => $persona->getKey(),
            'platform' => ManualPublicationAccount::PLATFORM_ZHIHU,
            'account_name' => 'GEOWorkFlow 知乎账号',
            'created_by_admin_id' => $admin->getKey(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.distribution.store'), [
                'name' => '知乎辅助',
                'domain' => 'zhihu.com',
                'endpoint_url' => 'https://zhihu.com',
                'channel_type' => 'cn_platform',
                'cn_platform' => 'zhihu',
                'cn_auth_mode' => 'browser_assist',
                'cn_mp_persona_id' => (int) $persona->getKey(),
                'status' => 'active',
            ])
            ->assertSessionDoesntHaveErrors();

        $channel = DistributionChannel::query()->where('name', '知乎辅助')->firstOrFail();
        $this->assertSame('browser_assist', $channel->resolvedCnPlatformConfig()['cn_auth_mode']);
        $this->assertSame((int) $persona->getKey(), (int) $channel->channel_config['cn_mp_persona_id']);
        $this->assertNull(DistributionChannelSecret::query()->where('distribution_channel_id', (int) $channel->id)->first());

        $category = Category::query()->create(['name' => '科技', 'slug' => 'tech-cn-feature']);
        $author = Author::query()->create(['name' => 'GEOWorkFlow']);
        $article = Article::query()->create([
            'title' => '知乎辅助发布测试',
            'slug' => 'zhihu-assist-test',
            'content' => '正文',
            'excerpt' => '摘要',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);

        $result = app(CnBrowserAssistDistributionService::class)->createForArticle($article, [$channel]);
        $this->assertSame([(int) $channel->id], array_keys($result['created']));
        $this->assertSame([], $result['failed']);

        $this->assertDatabaseHas('manual_publications', [
            'article_id' => (int) $article->id,
            'platform' => 'zhihu',
            'status' => ManualPublication::STATUS_DRAFT,
        ]);

        $second = app(CnBrowserAssistDistributionService::class)->createForArticle($article, [$channel]);
        $this->assertSame([], $second['created']);
        $this->assertSame('duplicate_or_missing_identity', $second['skipped'][(int) $channel->id]);
        $this->assertSame(1, ManualPublication::query()->where('article_id', (int) $article->id)->count());
    }

    public function test_task_enqueue_routes_browser_assist_channel_to_manual_publications(): void
    {
        Queue::fake();
        $admin = $this->admin();

        $persona = ManualPublicationPersona::query()->create(['name' => 'GEOWorkFlow 专家']);
        ManualPublicationAccount::query()->create([
            'persona_id' => $persona->getKey(),
            'platform' => ManualPublicationAccount::PLATFORM_XIAOHONGSHU,
            'account_name' => 'GEOWorkFlow 小红书账号',
            'created_by_admin_id' => $admin->getKey(),
        ]);

        $task = Task::query()->create([
            'name' => '国内平台分发任务',
            'status' => 'active',
            'schedule_enabled' => 1,
            'publish_scope' => 'local_and_distribution',
        ]);
        $assistChannel = DistributionChannel::query()->create([
            'name' => '小红书辅助',
            'domain' => 'xiaohongshu.com',
            'endpoint_url' => 'https://xiaohongshu.com',
            'channel_type' => 'cn_platform',
            'channel_config' => ['cn_platform' => 'xiaohongshu', 'cn_auth_mode' => 'browser_assist'],
            'status' => 'active',
            'created_by_admin_id' => (int) $admin->id,
        ]);
        app(DistributionOrchestrator::class)->syncTaskChannels($task, [(int) $assistChannel->id]);

        $category = Category::query()->create(['name' => '生活方式', 'slug' => 'life-cn-orch']);
        $author = Author::query()->create(['name' => 'GEOWorkFlow']);
        $article = Article::query()->create([
            'title' => '小红书编排测试',
            'slug' => 'xhs-orchestrator-test',
            'content' => '正文内容',
            'excerpt' => '摘要内容',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'task_id' => (int) $task->id,
            'status' => 'published',
            'review_status' => 'approved',
            'published_at' => now(),
        ]);

        $distributionIds = app(DistributionOrchestrator::class)->enqueueForArticle($article, throwOnFailure: true);

        $this->assertSame([], $distributionIds);
        $this->assertDatabaseCount('article_distributions', 0);
        $this->assertDatabaseHas('manual_publications', [
            'article_id' => (int) $article->id,
            'platform' => 'xiaohongshu',
            'status' => ManualPublication::STATUS_DRAFT,
        ]);
        Queue::assertNothingPushed();
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'cn_platform_admin',
            'password' => 'secret-123',
            'email' => 'cn-platform-admin@example.com',
            'display_name' => 'CN Platform Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
