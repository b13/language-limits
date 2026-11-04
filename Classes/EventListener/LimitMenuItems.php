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

use B13\LanguageLimits\Limit\LimitResolver;
use B13\LanguageLimits\Limit\PageAncestors;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Frontend\Event\FilterMenuItemsEvent;

/**
 * Removes pages from menus that are limited in the current language.
 */
#[AsEventListener(identifier: 'b13/language-limits/menu-items')]
final readonly class LimitMenuItems
{
    public function __construct(
        private LimitResolver $limitResolver,
        private PageAncestors $pageAncestors,
    ) {}

    public function __invoke(FilterMenuItemsEvent $event): void
    {
        $context = $event->getContext();
        /** @var LanguageAspect $languageAspect */
        $languageAspect = $context->getAspect('language');
        $languageId = $languageAspect->getId();
        if ($languageId <= 0) {
            return;
        }
        $ancestorsByParent = [];
        $menuItems = [];
        foreach ($event->getFilteredMenuItems() as $page) {
            $parentId = (int)($page['pid'] ?? 0);
            $ancestorsByParent[$parentId] ??= $this->pageAncestors->forParentPage($parentId, $context);
            $mode = $this->limitResolver->findForPage($page, $ancestorsByParent[$parentId], $languageId);
            $foundLanguageId = isset($page['_LOCALIZED_UID']) ? (int)($page['sys_language_uid'] ?? 0) : 0;
            if ($mode === null || $this->limitResolver->isShown($mode, $languageId, $foundLanguageId, $languageAspect->getFallbackChain())) {
                $menuItems[] = $page;
            }
        }
        $event->setFilteredMenuItems($menuItems);
    }
}
