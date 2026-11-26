<?php

declare(strict_types=1);

/**
 * Escape a value for HTML text and attribute contexts. Every value a view prints goes
 * through this, except the pre-rendered $content the layout receives.
 */
function escape(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
