<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Migrator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MigratorStatementsTest extends TestCase
{
    #[Test]
    public function itSplitsAFileIntoStatementsAndDropsCommentLines(): void
    {
        $sql = <<<'SQL'
            -- A comment that mentions a semicolon; it is still ignored.
            CREATE TABLE a (
                id INT NOT NULL
            );

            -- Second statement
            ALTER TABLE a ADD COLUMN b INT NULL;
            SQL;

        self::assertSame([
            "CREATE TABLE a (\n    id INT NOT NULL\n)",
            'ALTER TABLE a ADD COLUMN b INT NULL',
        ], Migrator::statements($sql));
    }

    #[Test]
    public function aSemicolonInsideALineDoesNotSplitTheStatement(): void
    {
        self::assertSame(
            ["INSERT INTO notes (body) VALUES ('first; second')"],
            Migrator::statements("INSERT INTO notes (body) VALUES ('first; second');\n"),
        );
    }

    #[Test]
    public function anEmptyFileHasNoStatements(): void
    {
        self::assertSame([], Migrator::statements("-- nothing here\n\n"));
    }
}
