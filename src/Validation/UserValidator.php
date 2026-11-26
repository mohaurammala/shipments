<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\Enums\Role;
use App\Services\Passwords;

/**
 * Field rules for a new account. Whether the email is already taken needs the database,
 * so UserService checks that.
 */
final readonly class UserValidator
{
    public const int MAX_EMAIL_LENGTH = 254;

    public function __construct(private Passwords $passwords)
    {
    }

    /**
     * @param array{email: string, role: string, password: string, password_confirmation: string} $input
     */
    public function validateNewUser(#[\SensitiveParameter] array $input): ValidationResult
    {
        $errors = [];

        if ($input['email'] === '') {
            $errors['email'] = 'Enter an email address.';
        } elseif (
            mb_strlen($input['email']) > self::MAX_EMAIL_LENGTH
            || filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Enter a valid email address, like name@example.com.';
        }

        if (Role::tryFrom($input['role']) === null) {
            $errors['role'] = 'Choose a role.';
        }

        $passwordProblem = $this->passwords->problemWith($input['password']);

        if ($passwordProblem !== null) {
            $errors['password'] = $passwordProblem;
        } elseif (!hash_equals($input['password'], $input['password_confirmation'])) {
            $errors['password_confirmation'] = 'The two passwords do not match.';
        }

        return new ValidationResult($errors);
    }
}
