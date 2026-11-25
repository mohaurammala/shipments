<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    /** @var resource */
    private $stream;
    
    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        $this->stream = $stream;
    }
    
    #[Test]
    public function itWritesOneJsonObjectPerLine(): void
    {
        $logger = new Logger($this->stream);
        
        $logger->info('first');
        $logger->error('second', ['ref' => 'a1b2c3']);
        
        $lines = $this->lines();
        self::assertCount(2, $lines);
        
        $entry = json_decode($lines[1], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $entry['level']);
        self::assertSame('second', $entry['message']);
        self::assertSame(['ref' => 'a1b2c3'], $entry['context']);
    }
    
    #[Test]
    public function itRedactsSecretLookingKeysAtAnyDepth(): void
    {
        (new Logger($this->stream))->warning('request failed', [
            'endpoint' => '/rates',
            'api_key' => 'sk_live_123',
            'headers' => ['Authorization' => 'Bearer abc'],
        ]);
        
        $line = $this->lines()[0];
        self::assertStringNotContainsString('sk_live_123', $line);
        self::assertStringNotContainsString('Bearer abc', $line);
        self::assertStringContainsString('/rates', $line);
    }
    
    /**
     * @return list<string>
     */
    private function lines(): array
    {
        rewind($this->stream);
        
        return array_values(array_filter(explode(PHP_EOL, (string)stream_get_contents($this->stream))));
    }
}
