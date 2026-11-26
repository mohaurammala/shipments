<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Pages;
use App\Http\Request;
use App\Http\Response;

/**
 * The signed-in landing page. A6 replaces it with the bookings list.
 */
final readonly class HomeController
{
    public function __construct(private Pages $pages)
    {
    }

    public function show(Request $request): Response
    {
        return $this->pages->render('home', ['title' => 'Bookings']);
    }
}
