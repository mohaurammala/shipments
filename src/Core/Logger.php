<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Writes one JSON object per line. Under Docker, stderr is what `docker compose logs` shows.
 */
final class Logger
{
    private const string SENSITIVE_KEY = '/pass|secret|token|key|authori[sz]ation|cookie/i';
    
    /** @var resource */
    private $stream;
    
    /**
     * @param resource|null $stream Defaults to stderr; tests pass php://memory.
     */
    public function __construct($stream = null)
    {
        $stream ??= fopen('php://stderr', 'wb');
        
        if (!is_resource($stream)) {
            throw new RuntimeException('Could not open the log stream.');
        }
        
        $this->stream = $stream;
    }
    
    /** @param array<array-key, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }
    
    /** @param array<array-key, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }
    
    /** @param array<array-key, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }
    
    /** @param array<array-key, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode(
            [
                'time' => gmdate('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'message' => $message,
                'context' => $this->redact($context),
            ],
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
        
        fwrite($this->stream, ($line ?: '{"level":"error","message":"Unencodable log entry"}') . PHP_EOL);
    }
    
    /**
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key) === 1) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }
        
        return $context;
    }
}