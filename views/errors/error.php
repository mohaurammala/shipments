<?php
/**
 * @var string $title
 * @var string $message
 * @var string|null $ref
 * @var Throwable|null $debug Only set when APP_DEBUG is on.
 */
?>
<h1><?= escape($title) ?></h1>
<p><?= escape($message) ?></p>
<?php if ($ref !== null): ?>
    <p>If you contact support, quote this reference: <strong><?= escape($ref) ?></strong></p>
<?php endif ?>
<p><a href="/">Back to the start</a></p>
<?php if ($debug !== null): ?>
    <details open>
        <summary>Debug details (APP_DEBUG is on)</summary>
        <pre><?= escape($debug::class . ': ' . $debug->getMessage()) ?>

<?= escape($debug->getFile() . ':' . $debug->getLine()) ?>


<?= escape($debug->getTraceAsString()) ?></pre>
    </details>
<?php endif ?>
