<?php

namespace App\Support\GeoFlow;

use App\Models\DistributionChannel;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * 主流 AI 工具高频引用信源预设库。
 *
 * tier=1 为国内 AI 助手回答引用频率最高的信源；cn_platform_key 关联 CnPlatformRegistry，
 * 表示 GEOWorkFlow 可直接分发建源；为空则为间接权威信源（百科/官媒/垂类），只能通过内容被检索收录。
 */
class AiCitationSourceRegistry
{
    public const DISABLED_SETTING_KEY = 'ai_citation_sources_disabled';

    public const CATEGORY_QA = 'qa';

    public const CATEGORY_NEWS = 'news';

    public const CATEGORY_TECH = 'tech';

    public const CATEGORY_LIFESTYLE = 'lifestyle';

    public const CATEGORY_VERTICAL = 'vertical';

    /**
     * 主流 AI 工具（检索引用侧）。
     *
     * @return array<string, string>
     */
    public static function aiTools(): array
    {
        return [
            'doubao' => '豆包',
            'deepseek' => 'DeepSeek',
            'kimi' => 'Kimi',
            'yuanbao' => '腾讯元宝',
            'wenxin' => '文心一言',
            'spark' => '讯飞星火',
            'chatgpt' => 'ChatGPT',
            'perplexity' => 'Perplexity',
            'gemini' => 'Google Gemini',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function sources(): array
    {
        return [
            'zhihu' => self::source('知乎', 'zhihu.com', self::CATEGORY_QA, 1, ['doubao', 'deepseek', 'kimi', 'chatgpt', 'perplexity'], 'zhihu'),
            'baike_baidu' => self::source('百度百科', 'baike.baidu.com', self::CATEGORY_QA, 1, ['doubao', 'wenxin', 'spark'], null, '百科词条无法直接发布，品牌词条需官方资质；系统会优先引用其中的品牌描述。'),
            'baike_tencent' => self::source('快懂百科', 'baike.com', self::CATEGORY_QA, 2, ['doubao', 'yuanbao', 'kimi'], null),
            'wikipedia' => self::source('维基百科', 'zh.wikipedia.org', self::CATEGORY_QA, 2, ['chatgpt', 'perplexity', 'gemini', 'deepseek'], null),
            'toutiao' => self::source('今日头条（头条号）', 'toutiao.com', self::CATEGORY_NEWS, 1, ['doubao', 'wenxin', 'spark'], 'toutiao'),
            'baijiahao' => self::source('百家号', 'baijiahao.baidu.com', self::CATEGORY_NEWS, 1, ['doubao', 'wenxin', 'spark'], 'baijiahao', '百度搜索系核心信源，文心系 AI 检索高频引用。'),
            'qq_news' => self::source('腾讯新闻（企鹅号）', 'qq.com', self::CATEGORY_NEWS, 1, ['doubao', 'yuanbao', 'wenxin'], 'qq_news'),
            'sina' => self::source('新浪（门户/新浪号）', 'sina.com.cn', self::CATEGORY_NEWS, 2, ['doubao', 'yuanbao', 'wenxin'], 'sina'),
            'sohu' => self::source('搜狐号', 'sohu.com', self::CATEGORY_NEWS, 2, ['doubao', 'wenxin', 'yuanbao'], 'sohu'),
            'wangyi' => self::source('网易号', '163.com', self::CATEGORY_NEWS, 2, ['doubao', 'yuanbao', 'wenxin'], 'wangyi'),
            'xinhua' => self::source('新华网', 'news.cn', self::CATEGORY_NEWS, 1, ['doubao', 'wenxin', 'yuanbao', 'spark'], null, '权威官媒，AI 回答事实类问题时优先采信；通过媒体转载/发稿渠道建源。'),
            'people' => self::source('人民网', 'people.com.cn', self::CATEGORY_NEWS, 1, ['doubao', 'wenxin', 'spark'], null, '权威官媒信源，同上，需借助媒体合作发布。'),
            '36kr' => self::source('36氪', '36kr.com', self::CATEGORY_NEWS, 3, ['doubao', 'kimi', 'deepseek'], null, '创投与科技热点检索高频媒体。'),
            'csdn' => self::source('CSDN', 'csdn.net', self::CATEGORY_TECH, 2, ['doubao', 'deepseek', 'kimi', 'spark'], 'csdn'),
            'juejin' => self::source('掘金', 'juejin.cn', self::CATEGORY_TECH, 3, ['deepseek', 'doubao'], null, '技术问答与教程类问题高频引用。'),
            'tencent_cloud' => self::source('腾讯云开发者社区', 'cloud.tencent.com', self::CATEGORY_TECH, 3, ['doubao', 'yuanbao', 'deepseek'], null),
            'aliyun_dev' => self::source('阿里云开发者社区', 'developer.aliyun.com', self::CATEGORY_TECH, 3, ['doubao', 'deepseek', 'kimi'], null),
            'weibo' => self::source('微博', 'weibo.com', self::CATEGORY_LIFESTYLE, 1, ['doubao', 'yuanbao', 'wenxin'], 'weibo'),
            'xiaohongshu' => self::source('小红书', 'xiaohongshu.com', self::CATEGORY_LIFESTYLE, 2, ['doubao', 'yuanbao', 'kimi'], 'xiaohongshu'),
            'dxy' => self::source('丁香医生', 'dxy.com', self::CATEGORY_VERTICAL, 3, ['doubao', 'wenxin'], null, '健康垂类问题的权威引用源。'),
            'zol' => self::source('中关村在线', 'zol.com.cn', self::CATEGORY_VERTICAL, 3, ['doubao', 'deepseek'], null, '3C 数码垂类引用源。'),
            'pcauto' => self::source('太平洋汽车', 'pcauto.com.cn', self::CATEGORY_VERTICAL, 3, ['doubao', 'wenxin'], null, '汽车垂类引用源。'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::sources());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(?string $key): ?array
    {
        $key = trim((string) $key);

        return self::sources()[$key] ?? null;
    }

    /**
     * 后台可配置的停用信源 key 列表。
     *
     * @return list<string>
     */
    public static function disabledKeys(): array
    {
        $stored = SiteSetting::query()
            ->where('setting_key', self::DISABLED_SETTING_KEY)
            ->value('setting_value');

        $decoded = json_decode((string) $stored, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_intersect(self::keys(), array_map(strval(...), $decoded)));
    }

    /**
     * @return array<string, array<string, mixed>> 仅启用的信源
     */
    public static function enabledSources(): array
    {
        $disabled = self::disabledKeys();

        return array_filter(self::sources(), static fn (string $key): bool => ! in_array($key, $disabled, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param  list<string>  $disabled
     */
    public static function setDisabledKeys(array $disabled): void
    {
        $normalized = array_values(array_intersect(self::keys(), array_map(
            static fn ($key): string => trim((string) $key),
            $disabled
        )));
        sort($normalized);

        SiteSetting::query()->updateOrCreate(
            ['setting_key' => self::DISABLED_SETTING_KEY],
            ['setting_value' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
    }

    /**
     * 按信源 key 归组已配置的分发渠道（cn_platform 且启用中）。
     *
     * @param  Collection<int, DistributionChannel>  $channels
     * @return array<string, list<array{id: int, name: string, auth_mode: string}>>
     */
    public static function coverageFor(Collection $channels): array
    {
        $coverage = [];

        foreach ($channels as $channel) {
            if ((string) $channel->channel_type !== DistributionChannel::TYPE_CN_PLATFORM) {
                continue;
            }

            $platform = trim((string) ($channel->resolvedCnPlatformConfig()['cn_platform'] ?? ''));
            if ($platform === '') {
                continue;
            }

            $coverage[$platform][] = [
                'id' => (int) $channel->id,
                'name' => (string) $channel->name,
                'auth_mode' => (string) ($channel->resolvedCnPlatformConfig()['cn_auth_mode'] ?? ''),
            ];
        }

        return $coverage;
    }

    /**
     * @param  list<string>  $citedBy
     * @return array<string, mixed>
     */
    private static function source(string $name, string $domain, string $category, int $tier, array $citedBy, ?string $cnPlatformKey, string $note = ''): array
    {
        return [
            'name' => $name,
            'domain' => $domain,
            'category' => $category,
            'tier' => $tier,
            'cited_by' => array_values(array_unique(array_filter($citedBy, static fn ($key): bool => array_key_exists((string) $key, self::aiTools())))),
            'cn_platform_key' => $cnPlatformKey,
            'publishable' => $cnPlatformKey !== null && CnPlatformRegistry::exists($cnPlatformKey),
            'note' => $note,
        ];
    }
}
