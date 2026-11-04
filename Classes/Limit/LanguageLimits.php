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
 * All limits of one record, keyed by language id, as stored in the JSON field
 * "tx_languagelimits":
 *
 *     {"1": {"mode": "never", "subpages": true}, "2": {"mode": "translated"}}
 *
 * Entries without a valid mode, and entries for the default language, are ignored.
 */
final readonly class LanguageLimits
{
    public const FIELD_NAME = 'tx_languagelimits';

    /**
     * @param array<int, LanguageLimit> $limits
     */
    private function __construct(
        private array $limits,
    ) {}

    public static function fromRecord(array $record): self
    {
        return self::fromValue($record[self::FIELD_NAME] ?? null);
    }

    public static function fromValue(mixed $value): self
    {
        if (is_string($value)) {
            if ($value === '') {
                return new self([]);
            }
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return new self([]);
            }
        }
        if (!is_array($value)) {
            return new self([]);
        }
        $limits = [];
        foreach ($value as $languageId => $configuration) {
            if (!is_numeric($languageId) || (int)$languageId <= 0 || !is_array($configuration)) {
                continue;
            }
            $mode = LimitMode::tryFrom((string)($configuration['mode'] ?? ''));
            if ($mode === null) {
                continue;
            }
            $limits[(int)$languageId] = new LanguageLimit($mode, (bool)($configuration['subpages'] ?? false));
        }
        return new self($limits);
    }

    public function forLanguage(int $languageId): ?LanguageLimit
    {
        return $this->limits[$languageId] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->limits === [];
    }

    /**
     * Returns the normalized representation that is stored in the database.
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->limits as $languageId => $limit) {
            $result[$languageId] = ['mode' => $limit->mode->value];
            if ($limit->appliesToSubpages) {
                $result[$languageId]['subpages'] = true;
            }
        }
        return $result;
    }
}
