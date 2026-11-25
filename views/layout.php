<?php
/**
 * Page shell. Bootstrap, navigation and the progress stepper arrive in A6.
 *
 * @var string $content The rendered template. Already escaped, so it is the one value
 *                      printed without e().
 * @var string|null $title
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($title) ? "{$title} · Shipments" : 'Shipments') ?></title>
</head>
<body>
<main>
<?= $content ?>
</main>
</body>
</html>
