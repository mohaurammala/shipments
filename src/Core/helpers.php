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

/**
 * Rand for display: "R 1 500.00". Not escaped: pass the result through escape().
 */
function rand_amount(App\Domain\Money $money): string
{
    return 'R ' . number_format(intdiv($money->cents, 100), 0, '.', ' ') . sprintf('.%02d', $money->cents % 100);
}

/**
 * A stored UTC time shown in South African time, e.g. "Thu 26 Nov, 16:00".
 * Not escaped: pass the result through escape().
 */
function local_time(?DateTimeImmutable $at, string $format = 'D j M, H:i'): string
{
    return $at === null ? '' : $at->setTimezone(new DateTimeZone('Africa/Johannesburg'))->format($format);
}
