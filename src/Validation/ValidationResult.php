<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * The outcome of checking a form: a message per field that failed, keyed by the field's
 * name or path (e.g. "email", "parcels.0.weight_kg"). Empty means valid.
 */
final readonly class ValidationResult
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public array $errors = [])
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function withError(string $field, string $message): self
    {
        return new self([...$this->errors, $field => $message]);
    }
}
