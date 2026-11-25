<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    #[Test]
    public function itReturnsAValue(): void
    {
        $config = new Config(['APP_ENV' => 'sandbox']);
        
        self::assertSame('sandbox', $config->get('APP_ENV'));
    }
    
    #[Test]
    public function itFallsBackToTheDefaultWhenAValueIsMissingOrEmpty(): void
    {
        $config = new Config(['EMPTY' => '']);
        
        self::assertSame('fallback', $config->get('MISSING', 'fallback'));
        self::assertSame('fallback', $config->get('EMPTY', 'fallback'));
    }
    
    #[Test]
    public function itNamesTheMissingKeyButNeverAnyValue(): void
    {
        $config = new Config(['DB_PASSWORD' => 'super-secret']);
        
        try {
            $config->get('DB_USERNAME');
            self::fail('Expected an exception for a missing key.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('DB_USERNAME', $e->getMessage());
            self::assertStringNotContainsString('super-secret', $e->getMessage());
        }
    }
    
    #[Test]
    public function itParsesWholeNumbersAndRejectsAnythingElse(): void
    {
        $config = new Config(['PORT' => '3306', 'BAD' => '33.5']);
        
        self::assertSame(3306, $config->int('PORT'));
        self::assertSame(20, $config->int('MISSING', 20));
        
        $this->expectException(RuntimeException::class);
        $config->int('BAD');
    }
    
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function booleanForms(): iterable
    {
        yield 'true' => ['true', true];
        yield 'false' => ['false', false];
        yield '1' => ['1', true];
        yield '0' => ['0', false];
        yield 'on' => ['on', true];
        yield 'off' => ['off', false];
    }
    
    #[Test]
    #[DataProvider('booleanForms')]
    public function itParsesCommonBooleanForms(string $raw, bool $expected): void
    {
        self::assertSame($expected, (new Config(['FLAG' => $raw]))->bool('FLAG'));
    }
    
    #[Test]
    public function itRejectsAnUnrecognisedBoolean(): void
    {
        $this->expectException(RuntimeException::class);
        
        (new Config(['FLAG' => 'maybe']))->bool('FLAG');
    }
    
    #[Test]
    public function realEnvironmentVariablesWinOverDotenvValues(): void
    {
        $_ENV['CONFIG_TEST_KEY'] = 'from-dotenv';
        putenv('CONFIG_TEST_KEY=from-docker');
        
        try {
            self::assertSame('from-docker', Config::fromEnvironment()->get('CONFIG_TEST_KEY'));
        } finally {
            unset($_ENV['CONFIG_TEST_KEY']);
            putenv('CONFIG_TEST_KEY');
        }
    }
}