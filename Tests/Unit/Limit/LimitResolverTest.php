<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "language_limits" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\LanguageLimits\Tests\Unit\Limit;

use B13\LanguageLimits\Limit\LimitMode;
use B13\LanguageLimits\Limit\LimitResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LimitResolverTest extends UnitTestCase
{
    public static function isShownDataProvider(): array
    {
        // Requested language 1 with the fallback chain 2, 0
        $chainWithDefault = [2, 0, 'pageNotFound'];
        $chainWithoutDefault = [2, 'pageNotFound'];
        return [
            'never, translated' => [LimitMode::Never, 1, $chainWithDefault, false],
            'always, not shown by TYPO3' => [LimitMode::Always, null, $chainWithDefault, true],
            'translated, translated' => [LimitMode::Translated, 1, $chainWithDefault, true],
            'translated, fallback language' => [LimitMode::Translated, 2, $chainWithDefault, false],
            'translated, default language' => [LimitMode::Translated, 0, $chainWithDefault, false],
            'translated, not shown by TYPO3' => [LimitMode::Translated, null, $chainWithDefault, false],
            'fallback, translated' => [LimitMode::Fallback, 1, $chainWithoutDefault, true],
            'fallback, fallback language' => [LimitMode::Fallback, 2, $chainWithoutDefault, true],
            'fallback, default language in chain' => [LimitMode::Fallback, 0, $chainWithDefault, true],
            'fallback, default language not in chain' => [LimitMode::Fallback, 0, $chainWithoutDefault, false],
            'fallback, language outside the chain' => [LimitMode::Fallback, 3, $chainWithDefault, false],
            'fallback, not shown by TYPO3' => [LimitMode::Fallback, null, $chainWithDefault, false],
            'fallback, chain with numeric strings' => [LimitMode::Fallback, 2, ['2', '0'], true],
        ];
    }

    #[DataProvider('isShownDataProvider')]
    #[Test]
    public function isShownDecidesByMode(LimitMode $mode, ?int $foundLanguageId, array $fallbackChain, bool $expected): void
    {
        self::assertSame($expected, (new LimitResolver())->isShown($mode, 1, $foundLanguageId, $fallbackChain));
    }

    #[Test]
    public function ownLimitOfPageIsUsed(): void
    {
        $page = ['uid' => 3, 'pid' => 2, 'tx_languagelimits' => '{"1":{"mode":"translated"}}'];
        $ancestors = [['uid' => 2, 'pid' => 0, 'tx_languagelimits' => '{"1":{"mode":"never","subpages":true}}']];

        self::assertSame(LimitMode::Translated, (new LimitResolver())->findForPage($page, $ancestors, 1));
    }

    #[Test]
    public function limitOfClosestAncestorForSubpagesIsUsed(): void
    {
        $page = ['uid' => 4, 'pid' => 3];
        $ancestors = [
            ['uid' => 3, 'pid' => 2, 'tx_languagelimits' => '{"1":{"mode":"translated"}}'],
            ['uid' => 2, 'pid' => 1, 'tx_languagelimits' => '{"1":{"mode":"fallback","subpages":true}}'],
            ['uid' => 1, 'pid' => 0, 'tx_languagelimits' => '{"1":{"mode":"never","subpages":true}}'],
        ];

        self::assertSame(LimitMode::Fallback, (new LimitResolver())->findForPage($page, $ancestors, 1));
        self::assertNull((new LimitResolver())->findForPage($page, $ancestors, 2));
    }

    #[Test]
    public function alwaysIsNotAvailableForPages(): void
    {
        $page = ['uid' => 3, 'pid' => 0, 'tx_languagelimits' => '{"1":{"mode":"always"}}'];

        self::assertNull((new LimitResolver())->findForPage($page, [], 1));
    }

    #[Test]
    public function noLimitAppliesToDefaultLanguage(): void
    {
        $record = ['uid' => 3, 'pid' => 0, 'tx_languagelimits' => '{"1":{"mode":"never"}}'];

        self::assertNull((new LimitResolver())->findForPage($record, [], 0));
        self::assertNull((new LimitResolver())->findForRecord($record, 0));
        self::assertSame(LimitMode::Never, (new LimitResolver())->findForRecord($record, 1));
    }

    #[Test]
    public function ancestorsAreFoundInRootlineOfAnyOrder(): void
    {
        $rootLine = [
            ['uid' => 1, 'pid' => 0],
            ['uid' => 4, 'pid' => 2],
            ['uid' => 2, 'pid' => 1],
            ['uid' => 7, 'pid' => 1],
        ];

        $ancestors = (new LimitResolver())->findAncestors(['uid' => 5, 'pid' => 4], $rootLine);

        self::assertSame([4, 2, 1], array_column($ancestors, 'uid'));
    }

    #[Test]
    public function ancestorsStopAtLoops(): void
    {
        $rootLine = [
            ['uid' => 2, 'pid' => 3],
            ['uid' => 3, 'pid' => 2],
        ];

        $ancestors = (new LimitResolver())->findAncestors(['uid' => 4, 'pid' => 2], $rootLine);

        self::assertSame([2, 3], array_column($ancestors, 'uid'));
    }
}
