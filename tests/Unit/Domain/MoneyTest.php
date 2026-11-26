<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function amounts(): iterable
    {
        yield 'whole rands' => ['1500', 150000, '1500.00'];
        yield 'one decimal' => ['1500.5', 150050, '1500.50'];
        yield 'two decimals' => ['0.10', 10, '0.10'];
        yield 'zero' => ['0', 0, '0.00'];
    }

    #[Test]
    #[DataProvider('amounts')]
    public function anAmountIsReadExactlyIntoCents(string $input, int $cents, string $decimal): void
    {
        $money = Money::fromDecimal($input);

        self::assertSame($cents, $money->cents);
        self::assertSame($decimal, $money->toDecimal());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAmounts(): iterable
    {
        yield 'empty' => [''];
        yield 'negative' => ['-5'];
        yield 'three decimals' => ['1.234'];
        yield 'scientific notation' => ['1e3'];
        yield 'currency symbol' => ['R1500'];
        yield 'thousands separator' => ['1,500'];
        yield 'words' => ['lots'];
    }

    #[Test]
    #[DataProvider('notAmounts')]
    public function anythingElseIsRefused(string $input): void
    {
        self::assertFalse(Money::isDecimal($input));

        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal($input);
    }

    #[Test]
    public function centsAddUpExactly(): void
    {
        $total = Money::fromDecimal('0.10')->cents + Money::fromDecimal('0.20')->cents;

        self::assertSame('0.30', Money::fromCents($total)->toDecimal());
    }
}
