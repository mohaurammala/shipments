<?php
/**
 * @var string $title
 * @var string $message
 * @var string|null $ref
 * @var Throwable|null $debug Only set when APP_DEBUG is on.
 */
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body p-4">
<h1><?= escape($title) ?></h1>
                <p><?= escape($message) ?></p>
                <?php if ($ref !== null): ?>
                    <p>If you contact support, quote this reference: <strong><?= escape($ref) ?></strong></p>
                <?php endif ?>
                <a class="btn btn-primary" href="/">Back to the start</a>
            </div>
        </div>
        <?php if ($debug !== null): ?>
            <details class="mt-3" open>
                <summary>Debug details (APP_DEBUG is on)</summary>
                <pre class="small bg-body p-3 border rounded"><?= escape($debug::class . ': ' . $debug->getMessage()) ?>

<?= escape($debug->getFile() . ':' . $debug->getLine()) ?>


<?= escape($debug->getTraceAsString()) ?></pre>
            </details>
        <?php endif ?>
    </div>
</div>
