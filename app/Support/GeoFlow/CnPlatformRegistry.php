<?php

namespace App\Support\GeoFlow;

/**
 * 国内 AI 高引用信源平台预设登记表。
 *
 * tier=1 为当前国内主流 AI 助手检索引用频率最高的平台，用于后台"着重添加"排序与标识。
 */
class CnPlatformRegistry
{
    public const AUTH_OAUTH2 = 'oauth2';

    public const AUTH_API = 'api';

    public const AUTH_BROWSER_ASSIST = 'browser_assist';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'zhihu' => [
                'name' => '知乎',
                'domain' => 'zhihu.com',
                'tier' => 1,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST],
                'manual_platform' => 'zhihu',
                'write_url' => 'https://zhuanlan.zhihu.com/write',
                'docs_url' => 'https://www.zhihu.com',
                'note' => '知乎无公开个人内容发布 API，使用浏览器辅助发布（扫码登录配套扩展后一键投递）。',
            ],
            'toutiao' => [
                'name' => '今日头条（头条号）',
                'domain' => 'toutiao.com',
                'tier' => 1,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST, self::AUTH_API],
                'manual_platform' => 'toutiao',
                'write_url' => 'https://mp.toutiao.com/profile_v4/graphic/publish',
                'docs_url' => 'https://mp.toutiao.com',
                'note' => '开放接口需头条号资质与审核；未获得凭据前建议用浏览器辅助发布。',
            ],
            'baijiahao' => [
                'name' => '百家号',
                'domain' => 'baijiahao.baidu.com',
                'tier' => 1,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST, self::AUTH_API],
                'manual_platform' => 'baijiahao',
                'write_url' => 'https://baijiahao.baidu.com/builder/rc/edit',
                'docs_url' => 'https://baijiahao.baidu.com',
                'note' => '百度搜索系核心信源；开放接口需账号权限，默认走浏览器辅助发布。',
            ],
            'weibo' => [
                'name' => '微博',
                'domain' => 'weibo.com',
                'tier' => 1,
                'content_kind' => 'short',
                'modes' => [self::AUTH_OAUTH2, self::AUTH_BROWSER_ASSIST],
                'manual_platform' => 'weibo',
                'write_url' => 'https://card.weibo.com/article/v3/editor',
                'docs_url' => 'https://open.weibo.com/wiki/2/statuses/share',
                'note' => '走官方 OAuth2 扫码授权发布分享动态（标题+摘要+原文链接引流）。',
                'api_base' => 'https://api.weibo.com',
                'authorize_url' => 'https://open.weibo.com/oauth2/authorize',
                'token_url' => 'https://open.weibo.com/oauth2/access_token',
            ],
            'sohu' => [
                'name' => '搜狐号',
                'domain' => 'sohu.com',
                'tier' => 2,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST, self::AUTH_API],
                'manual_platform' => 'sohu',
                'write_url' => 'https://mp.sohu.com',
                'docs_url' => 'https://mp.sohu.com',
                'note' => '新闻系 AI 检索常引信源；默认浏览器辅助发布，有接口凭据可直连。',
            ],
            'wangyi' => [
                'name' => '网易号',
                'domain' => '163.com',
                'tier' => 2,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST, self::AUTH_API],
                'manual_platform' => 'wangyi',
                'write_url' => 'https://mp.163.com',
                'docs_url' => 'https://mp.163.com',
                'note' => '默认浏览器辅助发布，有接口凭据可直连。',
            ],
            'qq_news' => [
                'name' => '腾讯新闻（企鹅号）',
                'domain' => 'qq.com',
                'tier' => 2,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST, self::AUTH_API],
                'manual_platform' => 'qq_news',
                'write_url' => 'https://om.qq.com',
                'docs_url' => 'https://om.qq.com',
                'note' => '默认浏览器辅助发布，有接口凭据可直连。',
            ],
            'sina' => [
                'name' => '新浪（新浪号/门户投稿）',
                'domain' => 'sina.com.cn',
                'tier' => 2,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST],
                'manual_platform' => 'sina',
                'write_url' => 'https://sina.com.cn',
                'docs_url' => 'https://weibo.com',
                'note' => '新浪系发布入口按账号类型分散，默认浏览器辅助发布。',
            ],
            'csdn' => [
                'name' => 'CSDN',
                'domain' => 'csdn.net',
                'tier' => 2,
                'content_kind' => 'article',
                'modes' => [self::AUTH_BROWSER_ASSIST, self::AUTH_API],
                'manual_platform' => 'csdn',
                'write_url' => 'https://blog.csdn.net',
                'docs_url' => 'https://blog.csdn.net',
                'note' => '技术类问题 AI 引用高频来源；默认浏览器辅助发布，有博客接口凭据可直连。',
            ],
            'xiaohongshu' => [
                'name' => '小红书',
                'domain' => 'xiaohongshu.com',
                'tier' => 3,
                'content_kind' => 'note',
                'modes' => [self::AUTH_BROWSER_ASSIST],
                'manual_platform' => 'xiaohongshu',
                'write_url' => 'https://creator.xiaohongshu.com/publish/publish',
                'docs_url' => 'https://creator.xiaohongshu.com',
                'note' => '生活类问答 AI 引用增长快；发布接口仅面向合作账号，默认浏览器辅助发布。',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(?string $key): ?array
    {
        $key = trim((string) $key);

        return self::all()[$key] ?? null;
    }

    public static function exists(?string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * @return list<string>
     */
    public static function modesFor(?string $key): array
    {
        return array_values((array) (self::get($key)['modes'] ?? []));
    }

    public static function supportsMode(?string $key, ?string $mode): bool
    {
        return $mode !== null
            && $mode !== ''
            && in_array($mode, self::modesFor($key), true);
    }

    public static function isBrowserAssistMode(?string $mode): bool
    {
        return (string) $mode === self::AUTH_BROWSER_ASSIST;
    }

    public static function isDirectApiMode(?string $mode): bool
    {
        return in_array((string) $mode, [self::AUTH_API, self::AUTH_OAUTH2], true);
    }

    /**
     * 按 AI 引用热度分级排序的平台列表（用于后台预设卡片）。
     *
     * @return list<array<string, mixed>>
     */
    public static function sortedPresets(): array
    {
        $rows = [];
        foreach (self::all() as $key => $platform) {
            $rows[] = $platform + ['key' => $key];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['tier'], $a['key']] <=> [$b['tier'], $b['key']]);

        return $rows;
    }

    public static function defaultTitleLimit(?string $key): int
    {
        return match (self::get($key)['content_kind'] ?? 'article') {
            'short' => 60,
            'note' => 40,
            default => 80,
        };
    }

    public static function defaultSummaryLimit(?string $key): int
    {
        return match (self::get($key)['content_kind'] ?? 'article') {
            'short' => 120,
            'note' => 200,
            default => 300,
        };
    }

    /**
     * @return list<string>
     */
    public static function authModes(): array
    {
        return [self::AUTH_BROWSER_ASSIST, self::AUTH_OAUTH2, self::AUTH_API];
    }
}
