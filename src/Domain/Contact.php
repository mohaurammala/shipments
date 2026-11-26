<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * The person the courier deals with at a collection or delivery address.
 */
final readonly class Contact
{
    public function __construct(
        public string $name,
        public string $mobile,
        public string $email,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data Input ShipmentValidator has accepted.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            mobile: self::normaliseMobile((string) $data['mobile']),
            email: mb_strtolower((string) $data['email']),
        );
    }

    /**
     * "082 123 4567", "082-123-4567" and "+27 82 123 4567" are one number: +27821234567.
     * The validator checks the result of this, so both agree on what a number is.
     */
    public static function normaliseMobile(string $mobile): string
    {
        $compact = preg_replace('/[\s\-().]/', '', $mobile) ?? $mobile;

        return str_starts_with($compact, '0') ? '+27' . substr($compact, 1) : $compact;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'mobile' => $this->mobile, 'email' => $this->email];
    }
}
