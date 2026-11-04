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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;
use TYPO3\CMS\Frontend\Event\ModifyPageLinkConfigurationEvent;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

/**
 * Does not link to pages that are limited in the target language: the link text
 * is rendered without a link, as TYPO3 does for pages that are not available.
 *
 * The target page and language are known when the link configuration is built,
 * the link text only after the link is generated. The first step marks the link
 * configuration, the second one cancels the link.
 */
final readonly class LimitPageLinks
{
    private const MARKER = 'tx_languagelimits_limited';

    public function __construct(
        private LimitResolver $limitResolver,
        private PageAncestors $pageAncestors,
        private PageRepository $pageRepository,
        private SiteFinder $siteFinder,
        private Context $context,
    ) {}

    #[AsEventListener(identifier: 'b13/language-limits/page-link-configuration')]
    public function markLimitedPage(ModifyPageLinkConfigurationEvent $event): void
    {
        $configuration = $event->getConfiguration();
        $page = $event->getPage();
        $siteLanguage = $this->getTargetLanguage($page, (string)($configuration['language'] ?? 'current'), $event);
        if ($siteLanguage === null || $siteLanguage->getLanguageId() <= 0) {
            return;
        }
        $languageId = $siteLanguage->getLanguageId();
        $ancestors = $this->pageAncestors->forParentPage((int)($page['pid'] ?? 0), $this->context);
        $mode = $this->limitResolver->findForPage($page, $ancestors, $languageId);
        if ($mode === null) {
            return;
        }
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($siteLanguage);
        $overlaidPage = $this->pageRepository->getPageOverlay($page, $languageAspect);
        $foundLanguageId = isset($overlaidPage['_LOCALIZED_UID']) ? (int)($overlaidPage['sys_language_uid'] ?? 0) : 0;
        if (!$this->limitResolver->isShown($mode, $languageId, $foundLanguageId, $languageAspect->getFallbackChain())) {
            $configuration[self::MARKER] = true;
            $event->setConfiguration($configuration);
        }
    }

    #[AsEventListener(identifier: 'b13/language-limits/page-link')]
    public function removeLinkToLimitedPage(AfterLinkIsGeneratedEvent $event): void
    {
        $linkResult = $event->getLinkResult();
        if ($linkResult->getType() !== LinkService::TYPE_PAGE || empty($linkResult->getLinkConfiguration()[self::MARKER])) {
            return;
        }
        throw new UnableToLinkException(
            'The target page is not available in the target language (language limits).',
            1793946345,
            null,
            (string)$linkResult->getLinkText()
        );
    }

    private function getTargetLanguage(array $page, string $language, ModifyPageLinkConfigurationEvent $event): ?SiteLanguage
    {
        if ($language === 'current') {
            $currentLanguage = $event->getRequest()->getAttribute('language');
            if (!$currentLanguage instanceof SiteLanguage) {
                return null;
            }
            $language = (string)$currentLanguage->getLanguageId();
        }
        try {
            return $this->siteFinder->getSiteByPageId((int)($page['uid'] ?? 0))->getLanguageById((int)$language);
        } catch (SiteNotFoundException|\InvalidArgumentException) {
            return null;
        }
    }
}
