<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "language_limits" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\LanguageLimits\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FrontendRenderingTest extends FunctionalTestCase
{
    private const ALL_CONTENT = [
        'Translated element',
        '[DA] Translated element',
        'Untranslated element',
        'Never element',
        '[DA] Never element',
        'Only translated element',
        'Always element',
        'Fallback element',
    ];

    private const ALL_MENU_ITEMS = [
        'Products',
        '[DA] Products',
        'Untranslated page',
        'Hidden in DA',
        '[DA] Hidden in DA',
    ];
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/language_limits',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Pages.csv');
        // Inline, as the test instance may register this package under its directory name instead of its extension key
        $this->setUpFrontendRootPage(1, [], ['config' => (string)file_get_contents(__DIR__ . '/Fixtures/Frontend/Rendering.typoscript')]);
    }

    public static function contentDataProvider(): array
    {
        return [
            // Core shows translated elements only. "Always" adds the untranslated element in the default language.
            'strict' => [
                'strict',
                [],
                ['[DA] Translated element', 'Always element'],
            ],
            // Core keeps untranslated elements in the default language. "Translated" and "Fallback" (without
            // the default language in the fallback chain) remove them.
            'fallback without default language in chain' => [
                'fallback',
                [],
                ['[DA] Translated element', 'Untranslated element', 'Always element'],
            ],
            // With the default language in the fallback chain, "Fallback" keeps the untranslated element.
            'fallback with default language in chain' => [
                'fallback',
                [0],
                ['[DA] Translated element', 'Untranslated element', 'Always element', 'Fallback element'],
            ],
        ];
    }

    #[DataProvider('contentDataProvider')]
    #[Test]
    public function contentIsLimitedPerLanguage(string $fallbackType, array $fallbacks, array $expectedContent): void
    {
        $this->writeSiteConfiguration($fallbackType, $fallbacks);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/da/?id=2'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expectedContent, $this->extract('content', (string)$response->getBody()));
    }

    #[Test]
    public function limitsDoNotApplyToDefaultLanguage(): void
    {
        $this->writeSiteConfiguration('strict', []);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/?id=5'));
        self::assertSame(200, $response->getStatusCode());

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/?id=2'));
        self::assertSame(
            ['Translated element', 'Untranslated element', 'Never element', 'Only translated element', 'Always element', 'Fallback element'],
            $this->extract('content', (string)$response->getBody())
        );
        self::assertSame(['Products', 'Untranslated page', 'Hidden in DA'], $this->extract('menu-item', (string)$response->getBody()));
    }

    public static function pageAccessDataProvider(): array
    {
        return [
            'translated page without limit' => [2, 200],
            'page limited to "never"' => [5, 404],
            'subpage of a page limited to "never" for subpages' => [7, 404],
            // Core would render the page in the default language, as the fallback chain contains the default language
            'untranslated page limited to "translated"' => [4, 404],
        ];
    }

    #[DataProvider('pageAccessDataProvider')]
    #[Test]
    public function pagesAreLimitedPerLanguage(int $pageId, int $expectedStatusCode): void
    {
        $this->writeSiteConfiguration('fallback', [0]);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/da/?id=' . $pageId));

        self::assertSame($expectedStatusCode, $response->getStatusCode());
    }

    #[Test]
    public function menusDoNotContainLimitedPages(): void
    {
        $this->writeSiteConfiguration('fallback', [0]);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/da/?id=2'));

        self::assertSame(['[DA] Products'], $this->extract('menu-item', (string)$response->getBody()));
    }

    #[Test]
    public function linksToLimitedPagesAreRenderedAsText(): void
    {
        $this->writeSiteConfiguration('fallback', [0]);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/da/?id=2'));

        preg_match_all('#<page-link>(.*?)</page-link>#', (string)$response->getBody(), $matches);
        self::assertCount(4, $matches[1]);
        [$products, $untranslated, $hidden, $childOfHidden] = $matches[1];
        self::assertSame('<a href="/da/products">Products</a>', $products);
        // Core would link to the page, as it is shown in the default language
        self::assertSame('Untranslated page', $untranslated);
        self::assertSame('Hidden in DA', $hidden);
        self::assertSame('Child of hidden', $childOfHidden);
    }

    #[Test]
    public function linksInDefaultLanguageAreNotLimited(): void
    {
        $this->writeSiteConfiguration('fallback', [0]);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/?id=2'));

        preg_match_all('#<page-link>(.*?)</page-link>#', (string)$response->getBody(), $matches);
        self::assertCount(4, $matches[1]);
        foreach ($matches[1] as $link) {
            self::assertStringStartsWith('<a href=', $link);
        }
    }

    private function writeSiteConfiguration(string $fallbackType, array $fallbacks): void
    {
        $this->get(SiteWriter::class)->write('test', [
            'rootPageId' => 1,
            'base' => 'https://website.local/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'navigationTitle' => 'English',
                    'base' => '/',
                    'locale' => 'en_US.UTF-8',
                    'flag' => 'us',
                ],
                [
                    'languageId' => 1,
                    'title' => 'Dansk',
                    'navigationTitle' => 'Dansk',
                    'base' => '/da/',
                    'locale' => 'da_DK.UTF-8',
                    'flag' => 'dk',
                    'fallbackType' => $fallbackType,
                    'fallbacks' => implode(',', $fallbacks),
                ],
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function extract(string $tagName, string $html): array
    {
        preg_match_all('#<' . $tagName . '>(.*?)</' . $tagName . '>#', $html, $matches);
        $values = array_map('html_entity_decode', $matches[1]);
        foreach ($values as $value) {
            self::assertContains($value, $tagName === 'content' ? self::ALL_CONTENT : self::ALL_MENU_ITEMS);
        }
        return $values;
    }
}
