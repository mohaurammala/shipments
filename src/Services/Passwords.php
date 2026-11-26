<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hashing, checking and the rules for new passwords, in one place.
 *
 * Argon2id rather than PHP's default bcrypt: bcrypt silently ignores everything after the
 * 72nd byte (in PHP 8.5, a 73-byte password with a different last byte still matches),
 * while Argon2id uses the whole password and is built to resist cracking hardware.
 */
final readonly class Passwords
{
    public const int MIN_LENGTH = 12;

    /**
     * Long passphrases are welcome; a megabyte "password" is just a way too much.
     */
    public const int MAX_BYTES = 1024;

    /**
     * A real Argon2id hash of a random value nobody knows, with the same settings as real
     * hashes. Checking against it when an email is unknown makes that response take as long
     * as a wrong password, so timing does not reveal which emails have accounts.
     */
    private const string DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$NUo2TFRJOVlWYlF3SmlhUA$0yftaFGO13nBnG96CgogxDrt6V1QnoEnkcq/aZh5U6E';

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * @param string|null $hash Null when no account matched: the dummy hash is checked instead.
     */
    public function verify(#[\SensitiveParameter] string $password, ?string $hash): bool
    {
        $matches = password_verify($password, $hash ?? self::DUMMY_HASH);

        return $hash !== null && $matches;
    }

    /**
     * True for hashes made with an older algorithm or settings (e.g. bcrypt), which are
     * upgraded the next time that user signs in, the only moment the plain password exists.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }

    /**
     * Length is the rule that matters (NIST SP 800-63B): no forced mixes of symbols and digits.
     *
     * @return string|null A message for the user, or null when the password is acceptable.
     */
    public function problemWith(#[\SensitiveParameter] string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return sprintf('Use at least %d characters. A few unrelated words work well.', self::MIN_LENGTH);
        }

        if (strlen($password) > self::MAX_BYTES) {
            return 'That password is too long.';
        }

        return null;
    }
}
