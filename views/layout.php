<?php
/**
 * Page shell. Bootstrap, navigation and the progress stepper arrive in A6.
 *
 * @var string $content The rendered template. Already escaped, so it is the one value
 *                      printed without e().
 * @var string|null $title
 * @var App\Domain\User|null $currentUser Not set on error pages.
 * @var string|null $csrfToken Not set on error pages.
 * @var list<array{type: string, message: string}>|null $flash Not set on error pages.
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape(isset($title) ? "{$title} · Shipments" : 'Shipments') ?></title>
</head>
<body>
<header>
    <a href="/">Shipments</a>
    <?php if (isset($currentUser, $csrfToken)): ?>
        <?php if ($currentUser->isAdmin()): ?>
            <a href="/admin/users">Users</a>
        <?php endif ?>
        <span>Signed in as <?= escape($currentUser->email) ?> (<?= escape($currentUser->role->value) ?>)</span>
        <form method="post" action="/logout">
            <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
            <button type="submit">Sign out</button>
        </form>
    <?php endif ?>
</header>
<main>
<?php foreach ($flash ?? [] as $message): ?>
    <p role="<?= $message['type'] === 'error' ? 'alert' : 'status' ?>"><?= escape($message['message']) ?></p>
<?php endforeach ?>
<?= $content ?>
</main>
</body>
</html>
