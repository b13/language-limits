<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "language_limits" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\LanguageLimits\Form\Element;

use B13\LanguageLimits\Limit\LanguageLimits;
use B13\LanguageLimits\Limit\LimitMode;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Renders one row per language of the site of the record, with the limit for
 * that language and, for pages, whether the limit also applies to subpages.
 *
 * The submitted value is an array like the stored JSON:
 * [languageId => ['mode' => 'never', 'subpages' => '1']]
 */
final class LanguageLimitsElement extends AbstractFormElement
{
    private const LABEL_PREFIX = 'language_limits.db:';

    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $parameterArray = $this->data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];
        $readOnly = (bool)($config['readOnly'] ?? false);
        $isPage = $this->data['tableName'] === 'pages';
        $limits = LanguageLimits::fromValue($parameterArray['itemFormElValue'] ?? null);
        $fieldName = $parameterArray['itemFormElName'];
        $fieldId = StringUtility::getUniqueId('formengine-language-limits-');

        $fieldInformationResult = $this->renderFieldInformation();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $languages = $this->getLanguages();
        if ($languages === []) {
            $resultArray['html'] = $this->renderLabel($fieldId)
                . '<div class="formengine-field-item t3js-formengine-field-item">'
                . $fieldInformationResult['html']
                . '<p class="text-body-secondary" id="' . htmlspecialchars($fieldId) . '">'
                . htmlspecialchars($this->translate('element.noLanguages'))
                . '</p></div>';
            return $resultArray;
        }

        $modes = array_values(array_filter(
            LimitMode::cases(),
            static fn(LimitMode $mode): bool => !$isPage || $mode->isAvailableForPages()
        ));

        $html = [];
        $html[] = $this->renderLabel($fieldId);
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] = $fieldInformationResult['html'];
        $html[] = '<div class="table-fit" id="' . htmlspecialchars($fieldId) . '">';
        $html[] = '<table class="table table-striped table-hover">';
        $html[] = '<thead><tr>';
        $html[] = '<th>' . htmlspecialchars($this->translate('element.language')) . '</th>';
        $html[] = '<th>' . htmlspecialchars($this->translate('element.limit')) . '</th>';
        if ($isPage) {
            $html[] = '<th>' . htmlspecialchars($this->translate('element.subpages')) . '</th>';
        }
        $html[] = '</tr></thead><tbody>';
        foreach ($languages as $language) {
            $languageId = $language->getLanguageId();
            $limit = $limits->forLanguage($languageId);
            $selectId = $fieldId . '-' . $languageId;
            $disabled = $readOnly ? ' disabled' : '';
            $html[] = '<tr>';
            $html[] = '<td><label for="' . htmlspecialchars($selectId) . '">' . htmlspecialchars($language->getTitle()) . '</label></td>';
            $html[] = '<td><select class="form-select form-select-sm" id="' . htmlspecialchars($selectId) . '"'
                . ' name="' . htmlspecialchars($fieldName . '[' . $languageId . '][mode]') . '"' . $disabled . '>';
            $html[] = '<option value="">' . htmlspecialchars($this->translate('mode.none')) . '</option>';
            foreach ($modes as $mode) {
                $selected = $limit?->mode === $mode ? ' selected' : '';
                $html[] = '<option value="' . htmlspecialchars($mode->value) . '"' . $selected . '>'
                    . htmlspecialchars($this->translate('mode.' . $mode->value)) . '</option>';
            }
            $html[] = '</select></td>';
            if ($isPage) {
                $checked = $limit?->appliesToSubpages ? ' checked' : '';
                $html[] = '<td><div class="form-check form-switch">'
                    . '<input type="checkbox" class="form-check-input" value="1"'
                    . ' name="' . htmlspecialchars($fieldName . '[' . $languageId . '][subpages]') . '"'
                    . ' aria-label="' . htmlspecialchars($this->translate('element.subpages') . ': ' . $language->getTitle()) . '"'
                    . $checked . $disabled . '>'
                    . '</div></td>';
            }
            $html[] = '</tr>';
        }
        $html[] = '</tbody></table>';
        $html[] = '</div>';
        $html[] = '</div>';
        $resultArray['html'] = implode("\n", $html);
        return $resultArray;
    }

    /**
     * @return list<SiteLanguage>
     */
    private function getLanguages(): array
    {
        $site = $this->data['site'] ?? null;
        if (!$site instanceof Site) {
            return [];
        }
        return array_values(array_filter(
            $site->getAllLanguages(),
            static fn(SiteLanguage $language): bool => $language->getLanguageId() > 0
        ));
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(self::LABEL_PREFIX . $key);
    }
}
