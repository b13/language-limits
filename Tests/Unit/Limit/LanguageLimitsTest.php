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

use B13\LanguageLimits\Limit\LanguageLimits;
use B13\LanguageLimits\Limit\LimitMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LanguageLimitsTest extends UnitTestCase
{
    #[Test]
    public function limitsAreReadFromJson(): void
    {
        $subject = LanguageLimits::fromValue('{"1":{"mode":"never","subpages":true},"2":{"mode":"translated"}}');

        $danish = $subject->forLanguage(1);
        self::assertNotNull($danish);
        self::assertSame(LimitMode::Never, $danish->mode);
        self::assertTrue($danish->appliesToSubpages);
        $german = $subject->forLanguage(2);
        self::assertNotNull($german);
        self::assertSame(LimitMode::Translated, $german->mode);
        self::assertFalse($german->appliesToSubpages);
        self::assertNull($subject->forLanguage(3));
    }

    #[Test]
    public function limitsAreReadFromSubmittedFormValues(): void
    {
        $subject = LanguageLimits::fromValue([
            '1' => ['mode' => 'fallback', 'subpages' => '1'],
            '2' => ['mode' => ''],
        ]);

        self::assertSame([1 => ['mode' => 'fallback', 'subpages' => true]], $subject->toArray());
    }

    public static function invalidValuesDataProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'invalid json' => ['{"1":'],
            'scalar json' => ['5'],
            'unknown mode' => [['1' => ['mode' => 'sometimes']]],
            'default language' => [['0' => ['mode' => 'never']]],
            'non-numeric language' => [['de' => ['mode' => 'never']]],
            'entry is not an array' => [['1' => 'never']],
        ];
    }

    #[DataProvider('invalidValuesDataProvider')]
    #[Test]
    public function invalidValuesResultInNoLimits(mixed $value): void
    {
        $subject = LanguageLimits::fromValue($value);

        self::assertTrue($subject->isEmpty());
        self::assertSame([], $subject->toArray());
    }
}
