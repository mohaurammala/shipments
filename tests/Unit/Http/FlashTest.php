<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Flash;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySession;

final class FlashTest extends TestCase
{
    #[Test]
    public function messagesAreShownOnceInTheOrderTheyWereAdded(): void
    {
        $flash = new Flash(new ArraySession());
        $flash->success('Saved.');
        $flash->error('But one thing failed.');

        self::assertSame([
            ['type' => 'success', 'message' => 'Saved.'],
            ['type' => 'error', 'message' => 'But one thing failed.'],
        ], $flash->pull());
        self::assertSame([], $flash->pull(), 'A refresh must not show them again.');
    }

    #[Test]
    public function messagesSurviveIntoTheNextRequest(): void
    {
        $session = new ArraySession();
        (new Flash($session))->success('Account created.');

        self::assertSame('Account created.', (new Flash($session))->pull()[0]['message']);
    }
}
