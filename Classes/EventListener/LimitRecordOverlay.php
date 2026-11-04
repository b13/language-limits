<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "language_limits" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\LanguageLimits\EventListener;

use B13\LanguageLimits\Limit\LanguageLimits;
use B13\LanguageLimits\Limit\LimitMode;
use B13\LanguageLimits\Limit\LimitResolver;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\Event\AfterRecordLanguageOverlayEvent;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Applies the language limits of a record when PageRepository overlays it with
 * a translation. This covers the content object CONTENT, the Record API and the
 * overlay step of Extbase queries.
 *
 * Pages are handled when the page is resolved and in menus, as dropping a page
 * record from an overlay breaks rootlines.
 */
#[AsEventListener(identifier: 'b13/language-limits/record-overlay')]
final readonly class LimitRecordOverlay
{
    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
        private LimitResolver $limitResolver,
    ) {}

    public function __invoke(AfterRecordLanguageOverlayEvent $event): void
    {
        $table = $event->getTable();
        if ($table === 'pages' || !$this->tcaSchemaFactory->has($table)) {
            return;
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->hasField(LanguageLimits::FIELD_NAME) || !$schema->isLanguageAware()) {
            return;
        }
        $languageField = $schema->getCapability(TcaSchemaCapability::Language)->getLanguageField()->getName();
        $record = $event->getRecord();
        // Limits are stored in the default language record only
        if ((int)($record[$languageField] ?? 0) !== 0) {
            return;
        }
        $languageAspect = $event->getLanguageAspect();
        $requestedLanguageId = $languageAspect->getId();
        $mode = $this->limitResolver->findForRecord($record, $requestedLanguageId);
        if ($mode === null) {
            return;
        }

        if ($event->overlayingWasAttempted()) {
            $localizedRecord = $event->getLocalizedRecord();
        } else {
            // No overlay is done if the content falls back to the default language
            $localizedRecord = $record;
        }
        $foundLanguageId = $localizedRecord === null ? null : (int)($localizedRecord[$languageField] ?? 0);

        if ($mode === LimitMode::Always) {
            if ($localizedRecord === null) {
                $event->setLocalizedRecord($record);
            }
            return;
        }
        if (!$this->limitResolver->isShown($mode, $requestedLanguageId, $foundLanguageId, $languageAspect->getFallbackChain())) {
            $event->setLocalizedRecord(null);
        }
    }
}
