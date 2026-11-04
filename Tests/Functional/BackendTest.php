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

use B13\LanguageLimits\Form\Element\LanguageLimitsElement;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BackendTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/language_limits',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BackendUser.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('en');
    }

    #[Test]
    public function onlyValidLimitsAreStored(): void
    {
        $this->processDataMap([
            'tt_content' => [
                'NEW1' => [
                    'pid' => 2,
                    'header' => 'New element',
                    'tx_languagelimits' => [
                        1 => ['mode' => 'never', 'subpages' => '1'],
                        2 => ['mode' => ''],
                    ],
                ],
            ],
            'pages' => [
                2 => [
                    'tx_languagelimits' => [
                        1 => ['mode' => 'translated', 'subpages' => '1'],
                        2 => ['mode' => 'fallback'],
                    ],
                ],
            ],
        ]);

        $content = $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['tx_languagelimits'], 'tt_content', ['header' => 'New element'])->fetchAssociative();
        // Subpages only exist for pages
        self::assertSame(['1' => ['mode' => 'never']], json_decode((string)$content['tx_languagelimits'], true));

        $page = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select(['tx_languagelimits'], 'pages', ['uid' => 2])->fetchAssociative();
        self::assertSame(
            ['1' => ['mode' => 'translated', 'subpages' => true], '2' => ['mode' => 'fallback']],
            json_decode((string)$page['tx_languagelimits'], true)
        );
    }

    #[Test]
    public function translationsGetLimitsOfTheirDefaultLanguageRecord(): void
    {
        $this->get(SiteWriter::class)->write('test', [
            'rootPageId' => 1,
            'base' => 'https://website.local/',
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'base' => '/', 'locale' => 'en_US.UTF-8'],
                ['languageId' => 1, 'title' => 'Dansk', 'base' => '/da/', 'locale' => 'da_DK.UTF-8'],
            ],
        ]);
        $dataHandler = $this->get(DataHandler::class);
        $dataHandler->start([], ['tt_content' => [15 => ['localize' => 1]]]);
        $dataHandler->process_cmdmap();
        self::assertSame([], $dataHandler->errorLog);

        // Translations are created hidden, so the default restrictions must not apply
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $translation = $queryBuilder
            ->select('tx_languagelimits')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('l18n_parent', 15),
                $queryBuilder->expr()->eq('sys_language_uid', 1),
            )
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($translation);
        self::assertSame(['1' => ['mode' => 'translated']], json_decode((string)$translation['tx_languagelimits'], true));
    }

    #[Test]
    public function formElementOffersSubpagesForPagesOnly(): void
    {
        $pageHtml = $this->renderFormElement('pages', 5, '{"1":{"mode":"never","subpages":true}}');
        self::assertStringContainsString('name="data[pages][5][tx_languagelimits][1][mode]"', $pageHtml);
        self::assertStringContainsString('<option value="never" selected>', $pageHtml);
        self::assertMatchesRegularExpression('#name="data\[pages\]\[5\]\[tx_languagelimits\]\[1\]\[subpages\]"[^>]* checked#', $pageHtml);
        self::assertStringContainsString('name="data[pages][5][tx_languagelimits][2][subpages]"', $pageHtml);
        self::assertStringNotContainsString('value="always"', $pageHtml);
        self::assertStringNotContainsString('[tx_languagelimits][0]', $pageHtml);

        $contentHtml = $this->renderFormElement('tt_content', 16, '{"1":{"mode":"always"}}');
        self::assertStringContainsString('<option value="always" selected>', $contentHtml);
        self::assertStringNotContainsString('[subpages]', $contentHtml);
    }

    private function processDataMap(array $dataMap): void
    {
        $dataHandler = $this->get(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();
        self::assertSame([], $dataHandler->errorLog);
    }

    private function renderFormElement(string $table, int $uid, string $value): string
    {
        $site = new Site('test', 1, [
            'base' => 'https://website.local/',
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'base' => '/', 'locale' => 'en_US.UTF-8'],
                ['languageId' => 1, 'title' => 'Dansk', 'base' => '/da/', 'locale' => 'da_DK.UTF-8'],
                ['languageId' => 2, 'title' => 'Deutsch', 'base' => '/de/', 'locale' => 'de_DE.UTF-8'],
            ],
        ]);
        $config = ['type' => 'json', 'renderType' => 'languageLimits'];
        $node = $this->get(LanguageLimitsElement::class);
        $node->setData([
            'tableName' => $table,
            'fieldName' => 'tx_languagelimits',
            'databaseRow' => ['uid' => $uid],
            'site' => $site,
            'processedTca' => ['columns' => ['tx_languagelimits' => ['config' => $config]]],
            'parameterArray' => [
                'itemFormElValue' => json_decode($value, true),
                'itemFormElName' => 'data[' . $table . '][' . $uid . '][tx_languagelimits]',
                'fieldConf' => ['label' => 'Language limits', 'config' => $config],
            ],
        ]);
        return $node->render()['html'];
    }
}
