<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Passwords;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordsTest extends TestCase
{
    private Passwords $passwords;

    protected function setUp(): void
    {
        $this->passwords = new Passwords();
    }

    #[Test]
    public function passwordsAreHashedWithArgon2id(): void
    {
        $hash = $this->passwords->hash('correct horse battery');

        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($this->passwords->verify('correct horse battery', $hash));
        self::assertFalse($this->passwords->verify('correct horse batterz', $hash));
    }

    #[Test]
    public function everyByteOfALongPasswordCounts(): void
    {
        $long = str_repeat('a', 72);
        $hash = $this->passwords->hash($long . 'X');

        self::assertFalse($this->passwords->verify($long . 'Y', $hash), 'bcrypt would have accepted this');
    }

    #[Test]
    public function withNoAccountTheCheckStillRunsButNeverSucceeds(): void
    {
        self::assertFalse($this->passwords->verify('anything at all', null));
    }

    #[Test]
    public function oldBcryptHashesAreMarkedForUpgrade(): void
    {
        $bcrypt = password_hash('correct horse battery', PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertTrue($this->passwords->needsRehash($bcrypt));
        self::assertFalse($this->passwords->needsRehash($this->passwords->hash('correct horse battery')));
    }

    #[Test]
    public function lengthIsTheOnlyRuleForNewPasswords(): void
    {
        self::assertNotNull($this->passwords->problemWith('short'));
        self::assertNull($this->passwords->problemWith('twelve chars'));
        self::assertNull($this->passwords->problemWith('alllowercaseandfine'));
        self::assertNotNull($this->passwords->problemWith(str_repeat('x', Passwords::MAX_BYTES + 1)));
    }
}
