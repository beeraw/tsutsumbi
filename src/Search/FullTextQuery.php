<?php

declare(strict_types=1);

namespace App\Search;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Turns a raw search box string into something MariaDB boolean mode accepts.
 *
 * Boolean-mode operators are stripped rather than passed through: a stray "+"
 * or an unbalanced quote makes the whole query fail, and an agent or a human
 * typing into a search field never means them as operators.
 */
final readonly class FullTextQuery
{
    /**
     * Mirrors innodb_ft_min_token_size. Terms below it are absent from the
     * index, so a query made only of such terms has to fall back to LIKE.
     */
    public const int MIN_TOKEN_LENGTH = 3;

    /**
     * @param list<string> $terms
     */
    private function __construct(
        public string $raw,
        public array $terms,
    ) {
    }

    public static function fromRaw(string $raw): self
    {
        $normalized = preg_replace('/[+\-><()~*"@]+/u', ' ', trim($raw)) ?? '';
        $terms = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return new self(trim($raw), false === $terms ? [] : $terms);
    }

    public function isEmpty(): bool
    {
        return [] === $this->terms;
    }

    /**
     * True when at least one term is long enough for the FULLTEXT index to know
     * about it. Otherwise the caller must use the LIKE fallback.
     */
    public function isIndexable(): bool
    {
        foreach ($this->terms as $term) {
            if (mb_strlen($term) >= self::MIN_TOKEN_LENGTH) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every indexable term is required and matched as a prefix, so "expor csv"
     * finds "CSV export of statements". Short terms are dropped: keeping them
     * with a leading "+" would make the query match nothing at all.
     */
    public function toBooleanExpression(): string
    {
        $expressions = [];
        foreach ($this->terms as $term) {
            if (mb_strlen($term) >= self::MIN_TOKEN_LENGTH) {
                $expressions[] = '+'.$term.'*';
            }
        }

        return implode(' ', $expressions);
    }

    /**
     * @return string the pattern for the LIKE fallback, wildcards escaped
     */
    public function toLikePattern(): string
    {
        return '%'.addcslashes($this->raw, '%_\\').'%';
    }
}
