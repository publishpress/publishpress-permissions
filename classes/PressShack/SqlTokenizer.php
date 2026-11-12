<?php

namespace PressShack;

\defined('ABSPATH') || exit;

class SqlTokenizer extends SqlTokenizerBase
{
    public function __construct()
    {
        $this->separators = array_merge(
            $this->separators,
            ['post_status', 'post_author', 'post_date', 'post_date_gmt', 'post_parent', 'post_modified', 'post_modified_gmt', 'ID', 'post_name', 'guid']
        );
    }
}

/**
 * This is a simple sql tokenizer.
 *
 * It does NOT support multiline comments at this time. Derived from SqlParser
 * - http://www.tehuber.com/article.php?story=20081016164856267
 * Modified by Kevin Behrens to add ParseArg method, remove other methods and properties
 *
 * THIS CODE IS A PROTOTYPE/BETA
 *
 * @author Justin Carlson <justin.carlson@gmail.com>
 * @modified Kevin Behrens <kevin@agapetry.net>
 * @license LGPL 3
 * @version 0.0.4
 */
class SqlTokenizerBase
{
    var $querysections = ['alter', 'create', 'drop', 'select', 'delete', 'insert', 'update', 'from', 'where', 'limit', 'order'];
    var $operators = ['=', '<>', '<', '<=', '>', '>=', 'like', 'clike', 'slike', 'not', 'is', 'in', 'between'];
    var $separators = ['and'];
    var $startparens = ['{', '('];
    var $endparens = ['}', ')'];
    var $tokens = [',', ' '];

    /**
     * Simple SQL Tokenizer
     *
     * @param string $sqlQuery
     * @param string $arg_name
     * @return token array
     * @description ParseArg() is used by PressPermit to extract the post_type argument from WordPress SQL queries.  It is not tested for other usage.
     * @author Kevin Behrens <kevin@agapetry.net>
     */
    public function ParseArg($sqlQuery, $arg_name)
    {
        $tokens = $this->Tokenize(strtolower($sqlQuery));
        $return = [];

        if ($array_pos = array_search($arg_name, (array)$tokens)) {
            $ilim = count($tokens);
            for ($i = $array_pos + 1; $i < $ilim; $i++) {
                if (in_array($tokens[$i], $this->endparens) || in_array($tokens[$i], $this->separators))
                    return $return;

                if (
                    !in_array($tokens[$i], $this->tokens) && !in_array($tokens[$i], $this->startparens)
                    && !in_array($tokens[$i], $this->operators) && !in_array($tokens[$i], $this->querysections)
                ) {
                    $return[] = str_replace("'", "", $tokens[$i]);
                }
            }
        }

        return $return;
    }

    /**
     * function Tokenize
     *
     * @param string $sqlQuery
     * @return token array
     * @author Justin Carlson <justin.carlson@gmail.com>
     */
    private function Tokenize($sqlQuery, $cleanWhitespace = true)
    {
        /**
         * Strip extra whitespace from the query
         */
        if ($cleanWhitespace) {
            $sqlQuery = ltrim(preg_replace('/[\\s]{2,}/', ' ', $sqlQuery));
        }

        /**
         * Regular expression based on SQL::Tokenizer's Tokenizer.pm by Igor Sutton Lopes
         **/
        $regex = '('; # begin group
        $regex .= '(?:--|\\#)[\\ \\t\\S]*'; # inline comments
        $regex .= '|(?:<>|<=>|>=|<=|==|=|!=|!|<<|>>|<|>|\\|\\||\\||&&|&|-|\\+|\\*(?!\/)|\/(?!\\*)|\\%|~|\\^|\\?)'; # logical operators 
        $regex .= '|[\\[\\]\\(\\),;`]|\\\'\\\'(?!\\\')|\\"\\"(?!\\"")'; # empty single/double quotes
        $regex .= '|".*?(?:(?:""){1,}"|(?<!["\\\\])"(?!")|\\\\"{2})|\'.*?(?:(?:\'\'){1,}\'|(?<![\'\\\\])\'(?!\')|\\\\\'{2})'; # quoted strings
        $regex .= '|\/\\*[\\ \\t\\n\\S]*?\\*\/'; # c style comments
        $regex .= '|(?:[\\w:@]+(?:\\.(?:\\w+|\\*)?)*)'; # words, placeholders, database.table.column strings
        $regex .= '|[\t\ ]+';
        $regex .= '|[\.]'; #period

        $regex .= ')'; # end group

        // get global match
        preg_match_all('/' . $regex . '/smx', $sqlQuery, $result);

        // return tokens
        return $result[0];
    }
}
