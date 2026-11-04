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
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Event\AfterPageAndLanguageIsResolvedEvent;
use TYPO3\CMS\Frontend\Page\PageAccessFailureReasons;

/**
 * Responds with "page not found" if the requested page is limited in the
 * requested language.
 */
#[AsEventListener(identifier: 'b13/language-limits/page-access')]
final readonly class LimitPageAccess
{
    public function __construct(
        private LimitResolver $limitResolver,
        private ErrorController $errorController,
    ) {}

    public function __invoke(AfterPageAndLanguageIsResolvedEvent $event): void
    {
        $request = $event->getRequest();
        $siteLanguage = $request->getAttribute('language');
        if (!$siteLanguage instanceof SiteLanguage || $siteLanguage->getLanguageId() <= 0) {
            return;
        }
        $pageInformation = $event->getPageInformation();
        $page = $pageInformation->getPageRecord();
        $ancestors = $this->limitResolver->findAncestors($page, $pageInformation->getRootLine());
        $mode = $this->limitResolver->findForPage($page, $ancestors, $siteLanguage->getLanguageId());
        if ($mode === null) {
            return;
        }
        $foundLanguageId = isset($page['_LOCALIZED_UID']) ? (int)($page['sys_language_uid'] ?? 0) : 0;
        $fallbackChain = LanguageAspectFactory::createFromSiteLanguage($siteLanguage)->getFallbackChain();
        if ($this->limitResolver->isShown($mode, $siteLanguage->getLanguageId(), $foundLanguageId, $fallbackChain)) {
            return;
        }
        $event->setResponse($this->errorController->pageNotFoundAction(
            $request,
            'Page is not available in the requested language (language limits).',
            ['code' => PageAccessFailureReasons::LANGUAGE_NOT_AVAILABLE]
        ));
    }
}
