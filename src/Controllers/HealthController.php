<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Http\Request;
use App\Http\Response;
use PDOException;

/**
 * Public liveness check for Docker or a load balancer. It says only "ok" or
 * "unavailable": no versions, hostnames or error text for an outsider to learn from.
 */
final readonly class HealthController
{
    public function __construct(
        private Database $database,
        private Logger $logger,
    ) {
    }

    public function show(Request $request): Response
    {
        try {
            $this->database->pdo()->query('SELECT 1');
        } catch (PDOException $e) {
            $this->logger->warning('Health check failed: database unavailable', ['message' => $e->getMessage()]);

            return Response::text('unavailable', 503)->withHeader('Cache-Control', 'no-store');
        }

        return Response::text('ok')->withHeader('Cache-Control', 'no-store');
    }
}
