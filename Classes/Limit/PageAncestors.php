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

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\Page\PageNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Finds the ancestors of a page, to apply limits of ancestors that also apply to subpages.
 */
final readonly class PageAncestors
{
    public function __construct(
        private LimitResolver $limitResolver,
    ) {}

    /**
     * Returns the ancestors of a page with the given parent page, the parent page first.
     *
     * @return list<array>
     */
    public function forParentPage(int $parentPageId, Context $context): array
    {
        if ($parentPageId <= 0) {
            return [];
        }
        try {
            $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $parentPageId, '', $context)->get();
        } catch (PageNotFoundException) {
            return [];
        }
        foreach ($rootLine as $page) {
            if ((int)($page['uid'] ?? 0) === $parentPageId) {
                return [$page, ...$this->limitResolver->findAncestors($page, $rootLine)];
            }
        }
        return [];
    }
}
