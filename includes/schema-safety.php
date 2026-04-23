<?php
/**
 * schema.php static analysis — disallows includes / eval / dangerous function calls
 * without flagging the same word inside string literals (false positives for labels).
 */
defined( 'ABSPATH' ) || exit;

/**
 * Returns true if the given PHP source (full schema.php content) should be rejected.
 * Must be valid PHP; invalid source is treated as unsafe.
 *
 * @param string $code Full schema file contents, including `<?php` if present.
 */
function hero_is_schema_php_unsafe( string $code ): bool {
    if ( $code === '' ) {
        return false;
    }

    try {
        $tokens = token_get_all( $code, TOKEN_PARSE );
    } catch ( \ParseError $e ) {
        return true;
    }

    $danger_funcs = [ 'eval', 'exec', 'system', 'shell_exec', 'passthru', 'popen', 'proc_open' ];

    $n = count( $tokens );
    for ( $i = 0; $i < $n; $i++ ) {
        $t = $tokens[ $i ];
        if ( is_string( $t ) ) {
            continue;
        }

        $id   = $t[0];
        $text = $t[1];

        if ( hero_schema_safety_is_skipped_token( $id ) ) {
            continue;
        }

        if ( in_array( (int) $id, [ T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE ], true ) ) {
            return true;
        }
        if ( defined( 'T_EVAL' ) && (int) $id === (int) T_EVAL ) {
            return true;
        }

        if ( defined( 'T_START_HEREDOC' ) && $id === T_START_HEREDOC ) {
            $i = hero_schema_safety_skip_heredoc( $tokens, $i, $n );
            continue;
        }

        if ( $id === T_STRING ) {
            $name = strtolower( ltrim( $text, '\\' ) );
            if ( in_array( $name, $danger_funcs, true ) && hero_schema_safety_next_non_ws_is_open_paren( $tokens, $i + 1, $n ) ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param int $id
 */
function hero_schema_safety_is_skipped_token( $id ): bool {
    if ( in_array( (int) $id, [ T_WHITESPACE, T_INLINE_HTML, T_ENCAPSED_AND_WHITESPACE ], true ) ) {
        return true;
    }
    if ( in_array( (int) $id, [ T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG ], true ) ) {
        return true;
    }
    if ( (int) $id === T_CONSTANT_ENCAPSED_STRING ) {
        return true; // '...' and simple "..." — entire literal.
    }
    if ( defined( 'T_ML_COMMENT' ) && (int) $id === T_ML_COMMENT ) {
        return true;
    }

    return false;
}

/**
 * @param array<int, string|array{0:int,1:string,2:int}> $tokens
 */
function hero_schema_safety_next_non_ws_is_open_paren( array $tokens, int $start, int $n ): bool {
    for ( $j = $start; $j < $n; $j++ ) {
        $t = $tokens[ $j ];
        if ( is_string( $t ) ) {
            return $t === '(';
        }
        if ( in_array( (int) $t[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
            continue;
        }
        return false;
    }
    return false;
}

/**
 * @param array<int, string|array{0:int,1:string,2:int}> $tokens
 * @return int new index (last heredoc token consumed)
 */
function hero_schema_safety_skip_heredoc( array $tokens, int $start, int $n ): int {
    if ( ! defined( 'T_END_HEREDOC' ) ) {
        return $start;
    }
    for ( $i = $start + 1; $i < $n; $i++ ) {
        $t = $tokens[ $i ];
        if ( is_string( $t ) ) {
            continue;
        }
        if ( (int) $t[0] === T_END_HEREDOC ) {
            return $i;
        }
    }
    return $start;
}
