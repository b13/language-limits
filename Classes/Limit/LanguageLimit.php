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
 * The limit of one record for one language.
 */
final readonly class LanguageLimit
{
    public function __construct(
        public LimitMode $mode,
        public bool $appliesToSubpages = false,
    ) {}
}
