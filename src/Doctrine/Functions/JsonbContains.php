<?php

namespace App\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * PostgreSQL containment check for JSON columns.
 *
 * DQL:  JSONB_CONTAINS(c.types, :type) = true
 * SQL:  (c0_.types)::jsonb @> (?)::jsonb
 *
 * Replaces MySQL's JSON_CONTAINS(), which PostgreSQL does not provide.
 */
class JsonbContains extends FunctionNode
{
    private $haystack;

    private $needle;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->haystack = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->needle = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            '(%s)::jsonb @> (%s)::jsonb',
            $this->haystack->dispatch($sqlWalker),
            $this->needle->dispatch($sqlWalker),
        );
    }
}
