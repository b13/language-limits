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
 * Finds the limit that applies to a record in a language, and decides whether
 * the record is shown.
 */
final readonly class LimitResolver
{
    /**
     * A page uses its own limit for the language. Without one, the limit of the
     * closest ancestor page that applies its limit to subpages is used.
     *
     * @param array $page The page record (default language or overlaid)
     * @param list<array> $ancestors Ancestor page records, the parent page first
     */
    public function findForPage(array $page, array $ancestors, int $languageId): ?LimitMode
    {
        if ($languageId <= 0) {
            return null;
        }
        $limit = LanguageLimits::fromRecord($page)->forLanguage($languageId);
        if ($limit !== null) {
            return $limit->mode->isAvailableForPages() ? $limit->mode : null;
        }
        foreach ($ancestors as $ancestor) {
            $limit = LanguageLimits::fromRecord($ancestor)->forLanguage($languageId);
            if ($limit !== null && $limit->appliesToSubpages) {
                return $limit->mode->isAvailableForPages() ? $limit->mode : null;
            }
        }
        return null;
    }

    public function findForRecord(array $record, int $languageId): ?LimitMode
    {
        if ($languageId <= 0) {
            return null;
        }
        return LanguageLimits::fromRecord($record)->forLanguage($languageId)?->mode;
    }

    /**
     * @param int|null $foundLanguageId The language of the record version TYPO3 would show,
     *                                  null if TYPO3 would not show the record at all
     * @param array $fallbackChain The fallback chain of the language aspect, may contain non-numeric markers
     */
    public function isShown(LimitMode $mode, int $requestedLanguageId, ?int $foundLanguageId, array $fallbackChain): bool
    {
        return match ($mode) {
            LimitMode::Never => false,
            LimitMode::Always => true,
            LimitMode::Translated => $foundLanguageId === $requestedLanguageId,
            LimitMode::Fallback => $foundLanguageId !== null
                && ($foundLanguageId === $requestedLanguageId || in_array($foundLanguageId, self::numericFallbackChain($fallbackChain), true)),
        };
    }

    /**
     * Returns the ancestors of a page, the parent first, from a list of page
     * records that contains them (for example a rootline in any order).
     *
     * @param list<array> $pages
     * @return list<array>
     */
    public function findAncestors(array $page, array $pages): array
    {
        $pagesByUid = [];
        foreach ($pages as $candidate) {
            if (isset($candidate['uid'])) {
                $pagesByUid[(int)$candidate['uid']] = $candidate;
            }
        }
        $ancestors = [];
        $visited = [(int)($page['uid'] ?? 0) => true];
        $parentId = (int)($page['pid'] ?? 0);
        while ($parentId > 0 && isset($pagesByUid[$parentId]) && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $ancestors[] = $pagesByUid[$parentId];
            $parentId = (int)($pagesByUid[$parentId]['pid'] ?? 0);
        }
        return $ancestors;
    }

    /**
     * @return list<int>
     */
    private static function numericFallbackChain(array $fallbackChain): array
    {
        $languageIds = [];
        foreach ($fallbackChain as $languageId) {
            if (is_int($languageId) || (is_string($languageId) && ctype_digit($languageId))) {
                $languageIds[] = (int)$languageId;
            }
        }
        return $languageIds;
    }
}
