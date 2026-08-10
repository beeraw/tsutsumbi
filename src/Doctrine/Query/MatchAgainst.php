<?php

declare(strict_types=1);

namespace App\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;
use LogicException;

use function sprintf;

/**
 * Exposes the MariaDB FULLTEXT match to DQL:
 *
 *     MATCH_AGAINST(t.title, t.description, :query) > 0
 *
 * Boolean mode is used deliberately: it has no 50%-of-rows relevance threshold,
 * which natural-language mode applies and which makes small datasets — exactly
 * what a young project is — return nothing at all.
 */
final class MatchAgainst extends FunctionNode
{
    /**
     * @var list<PathExpression>
     */
    private array $columns = [];

    private ?Node $needle = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->columns[] = $parser->StateFieldPathExpression();

        $lexer = $parser->getLexer();
        while ($lexer->isNextToken(TokenType::T_COMMA)) {
            $parser->match(TokenType::T_COMMA);

            // A path expression reads as "alias . field"; anything else at this
            // position is the needle and closes the argument list.
            $glimpse = $lexer->glimpse();
            if (null !== $glimpse && TokenType::T_DOT === $glimpse->type) {
                $this->columns[] = $parser->StateFieldPathExpression();

                continue;
            }

            $this->needle = $parser->StringPrimary();
            break;
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        if (null === $this->needle) {
            throw new LogicException('MATCH_AGAINST requires a search term as its last argument.');
        }

        $columns = array_map(
            static fn (PathExpression $column): string => $sqlWalker->walkPathExpression($column),
            $this->columns,
        );

        return sprintf(
            'MATCH (%s) AGAINST (%s IN BOOLEAN MODE)',
            implode(', ', $columns),
            $this->needle->dispatch($sqlWalker),
        );
    }
}
