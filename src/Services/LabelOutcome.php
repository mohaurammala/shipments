<?php

declare(strict_types=1);

namespace App\Services;

final readonly class LabelOutcome
{
    /**
     * @param string|null $url A fresh signed link to the PDF, when it worked.
     * @param string|null $message Why not, in words safe to show, when it did not.
     */
    public function __construct(
        public ?string $url,
        public ?string $message,
    ) {
    }
}
