<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "language_limits" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\LanguageLimits\Limit;

/**
 * Defines in which case a record is shown in a language.
 *
 * The "found language" is the language of the record version that TYPO3
 * would show: the requested language, a language of the fallback chain,
 * or the default language (0).
 */
enum LimitMode: string
{
    /**
     * Only shown if a translation into the requested language exists.
     */
    case Translated = 'translated';

    /**
     * Shown if a translation into the requested language or into a language of
     * the fallback chain exists. The default language counts as fallback only if
     * "0" is part of the fallback chain of the site language.
     */
    case Fallback = 'fallback';

    /**
     * Never shown, even if a translation exists.
     */
    case Never = 'never';

    /**
     * Always shown: the translation if one exists, otherwise the default language
     * record. Only available for records, not for pages.
     */
    case Always = 'always';

    public function isAvailableForPages(): bool
    {
        return $this !== self::Always;
    }
}
