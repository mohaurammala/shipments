<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Logger;
use App\Core\View;
use ErrorException;
use Throwable;

/**
 * Turns every failure into a safe page. Client errors (HttpException) show their public
 * message. Anything else is logged in full with a short reference code, and the user sees
 * only a generic message and that code, so support can find the log entry.
 */
final readonly class ErrorHandler
{
    private const array FATAL_ERRORS = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    private const array TITLES = [
        400 => 'Bad request',
        403 => 'Not allowed',
        404 => 'Page not found',
        405 => 'Not allowed',
        500 => 'Something went wrong',
    ];

    public function __construct(
        private Logger $logger,
        private View $view,
        private bool $debug,
    ) {
    }

    /**
     * Catches what the front controller's try/catch cannot: warnings and notices (turned
     * into exceptions), exceptions thrown outside it, and fatal errors at shutdown.
     */
    public function register(): void
    {
        set_error_handler($this->handleError(...));
        set_exception_handler(function (Throwable $e): void {
            $this->send($this->toResponse($e));
        });
        register_shutdown_function($this->handleShutdown(...));
    }

    /**
     * A warning such as "Undefined array key" becomes an exception instead of letting the
     * request carry on with bad data. Errors silenced with @ or by error_reporting are left alone.
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public function toResponse(Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            $response = $this->page($e->status, $e->publicMessage);

            foreach ($e->headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $response;
        }

        $ref = bin2hex(random_bytes(3));

        // Server-side only. Code that expects a failure involving personal data (such as a
        // duplicate email) must catch it and throw its own message, so none reaches here.
        $this->logger->error('Unhandled exception', [
            'ref' => $ref,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'location' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->page(500, 'Something went wrong on our side. Please try again in a moment.', $ref, $this->debug ? $e : null);
    }

    private function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], self::FATAL_ERRORS, true)) {
            return;
        }

        $this->send($this->toResponse(
            new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']),
        ));
    }

    private function send(Response $response): void
    {
        // Drop anything half-rendered before the error, so the page is not a mix of both.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $response->send();
    }

    private function page(int $status, string $message, ?string $ref = null, ?Throwable $debug = null): Response
    {
        $title = self::TITLES[$status] ?? ($status >= 500 ? self::TITLES[500] : 'Request failed');

        try {
            $html = $this->view->render('errors/error', [
                'title' => $title,
                'message' => $message,
                'ref' => $ref,
                'debug' => $debug,
            ]);
        } catch (Throwable $viewError) {
            // The error page itself failed (e.g. a broken layout). Plain text still gives the
            // user the message and the reference code.
            $this->logger->error('Error page failed to render', [
                'ref' => $ref,
                'exception' => $viewError::class,
                'message' => $viewError->getMessage(),
            ]);

            return Response::text($message . ($ref === null ? '' : " Reference: {$ref}"), $status)
                ->withHeader('Cache-Control', 'no-store');
        }

        return Response::html($html, $status)->withHeader('Cache-Control', 'no-store');
    }
}
