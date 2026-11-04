<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "language_limits" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\LanguageLimits\Hooks;

use B13\LanguageLimits\Limit\LanguageLimits;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Stores only valid limits: the form element submits one entry per language,
 * also for languages without a limit.
 */
final class NormalizeLanguageLimits
{
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, string $table, string|int $id, DataHandler $dataHandler): void
    {
        if (!array_key_exists(LanguageLimits::FIELD_NAME, $incomingFieldArray)) {
            return;
        }
        $limits = LanguageLimits::fromValue($incomingFieldArray[LanguageLimits::FIELD_NAME])->toArray();
        if ($table === 'pages') {
            $incomingFieldArray[LanguageLimits::FIELD_NAME] = $limits;
            return;
        }
        // Inheritance to subpages only exists for pages
        foreach ($limits as $languageId => $limit) {
            unset($limits[$languageId]['subpages']);
        }
        $incomingFieldArray[LanguageLimits::FIELD_NAME] = $limits;
    }
}
