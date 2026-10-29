<?php

declare(strict_types=1);

/**
 * Free hosts such as InfinityFree disable php_uname() (it returns null there).
 * The Anthropic SDK calls it, unqualified, from the Anthropic\Core namespace to
 * fill a request header, so a strict-types TypeError stopped every AI request.
 * PHP looks up an unqualified function in the current namespace first, so this
 * safe version is used instead of the global one. Loaded before the SDK.
 */

namespace Anthropic\Core;

if (!\function_exists(__NAMESPACE__ . '\\php_uname')) {
    function php_uname(string $mode = 'a'): string {
        $value = \function_exists('php_uname') ? @\php_uname($mode) : null;
        return \is_string($value) ? $value : '';
    }
}
