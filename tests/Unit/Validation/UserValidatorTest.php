<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Services\Passwords;
use App\Validation\UserValidator;
use App\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserValidatorTest extends TestCase
{
    private const array VALID = [
        'email' => 'sipho@example.test',
        'role' => 'operator',
        'password' => 'twelve chars plus',
        'password_confirmation' => 'twelve chars plus',
    ];

    #[Test]
    public function aCompleteFormIsValid(): void
    {
        self::assertTrue($this->validate([])->isValid());
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'missing email' => [['email' => ''], 'email'];
        yield 'not an email' => [['email' => 'sipho-at-example'], 'email'];
        yield 'email too long' => [['email' => str_repeat('a', 250) . '@x.io'], 'email'];
        yield 'unknown role' => [['role' => 'superuser'], 'role'];
        yield 'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'];
        yield 'passwords differ' => [['password_confirmation' => 'twelve chars plus!'], 'password_confirmation'];
    }

    /**
     * @param array<string, string> $overrides
     */
    #[Test]
    #[DataProvider('invalidInput')]
    public function eachProblemIsReportedAgainstItsField(array $overrides, string $field): void
    {
        $result = $this->validate($overrides);

        self::assertFalse($result->isValid());
        self::assertNotNull($result->error($field));
    }

    #[Test]
    public function aShortPasswordIsNotAlsoReportedAsAMismatch(): void
    {
        $result = $this->validate(['password' => 'short', 'password_confirmation' => 'different']);

        self::assertSame(['password'], array_keys($result->errors));
    }

    /**
     * @param array<string, string> $overrides
     */
    private function validate(array $overrides): ValidationResult
    {
        /** @var array{email: string, role: string, password: string, password_confirmation: string} $input */
        $input = [...self::VALID, ...$overrides];

        return (new UserValidator(new Passwords()))->validateNewUser($input);
    }
}
