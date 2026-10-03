<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminSidebarLayoutStyleTest extends TestCase
{
    public function test_primary_navigation_and_recent_history_share_one_scrolling_region_with_a_fixed_account_bar(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
        $stabilityCss = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/admin-ui-v3-stability.css');

        self::assertStringContainsString(
            '.gf-sidebar__nav { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 12px 12px; scrollbar-color: var(--gf-gray-300) transparent; scrollbar-width: thin; }',
            $css,
        );
        self::assertStringContainsString(
            '.gf-sidebar__primary { flex: 0 0 auto; min-height: auto; overflow: visible; }',
            $css,
        );
        self::assertStringContainsString(
            '.gf-sidebar__recent { display: flex; flex: 0 0 auto;',
            $css,
        );
        self::assertStringContainsString('max-height: none; min-height: 0;', $css);
        self::assertStringContainsString(
            '.gf-sidebar__recent.is-collapsed { flex: 0 0 34px; min-height: 34px; }',
            $css,
        );
        self::assertStringContainsString(
            '.gf-sidebar__recent-body { flex: 0 0 auto; min-height: 0; overflow: visible; }',
            $css,
        );
        self::assertStringContainsString(
            '.gf-sidebar__recent-scroll { height: auto; overflow: visible;',
            $css,
        );
        self::assertStringContainsString(
            '@media (max-height: 640px) { .gf-sidebar__recent { margin-top: 0; padding-top: 0; } }',
            $css,
        );

        // Height-based hiding is obsolete now that the whole nav scrolls as one region.
        self::assertStringNotContainsString('max-height: 594px', $css);
        self::assertStringNotContainsString(
            ".gf-sidebar__recent { display: none; }",
            $css,
        );
        self::assertStringNotContainsString(
            "html[data-gf-sidebar-state='collapsed'] .gf-admin-v3 .gf-sidebar__recent {\n        display: none;",
            $stabilityCss,
        );

        $mobileRecentRestore = strpos(
            $stabilityCss,
            "html[data-gf-sidebar-state='collapsed'] .gf-admin-v3 .gf-sidebar__recent {\n        display: flex;",
        );
        self::assertIsInt($mobileRecentRestore);

        // The account bar must stay outside the scrolling nav (bottom safe area).
        self::assertStringContainsString(
            '.gf-sidebar__account-bar { align-items: center; display: flex; flex: 0 0 auto;',
            $css,
        );
    }
}
