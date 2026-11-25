<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Renders plain PHP templates from the views directory, optionally inside a layout.
 */
final readonly class View
{
    private const string TEMPLATE_NAME = '#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#';

    public function __construct(private string $directory)
    {
    }

    /**
     * @param array<string, mixed> $data Variables for the template; the layout also gets
     *                                   them, plus $content holding the rendered template.
     */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, [...$data, 'content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $template, array $data): string
    {
        $path = $this->resolve($template);

        // A static closure gives the template a clean scope: only $data's keys, no $this.
        $include = static function (string $__path, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__path;
        };

        ob_start();

        try {
            $include($path, $data);
        } catch (Throwable $e) {
            // Discard the half-rendered page so it never reaches the browser.
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Template names are lowercase path segments ("booking/form"), so "../" can never
     * point outside the views directory.
     */
    private function resolve(string $template): string
    {
        if (preg_match(self::TEMPLATE_NAME, $template) !== 1) {
            throw new InvalidArgumentException("Invalid template name: {$template}");
        }

        $path = "{$this->directory}/{$template}.php";

        if (!is_file($path)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        return $path;
    }
}
