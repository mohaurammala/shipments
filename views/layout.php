<?php
/**
 * Page shell: navigation, flash messages and the page's content. Bootstrap and app.js are
 * served from /assets (no CDN), so the content security policy can stay "self" only.
 *
 * @var string $content The rendered template. Already escaped, so it is the one value
 *                      printed without escape().
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
    <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script src="/assets/app.js" defer></script>
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-md bg-body border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="/">Shipments</a>
        <?php if (isset($currentUser, $csrfToken)): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                    aria-controls="main-nav" aria-expanded="false" aria-label="Show menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="main-nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/">Bookings</a></li>
                    <li class="nav-item"><a class="nav-link" href="/bookings/new">New booking</a></li>
                    <?php if ($currentUser->isAdmin()): ?>
                        <li class="nav-item"><a class="nav-link" href="/admin/users">Users</a></li>
                    <?php endif ?>
                </ul>
                <span class="navbar-text small me-md-3">
                    <?= escape($currentUser->email) ?> (<?= escape($currentUser->role->value) ?>)
                </span>
                <form method="post" action="/logout" class="py-2 py-md-0">
                    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Sign out</button>
                </form>
            </div>
        <?php endif ?>
    </div>
</nav>
<main class="container pb-5">
    <?php foreach ($flash ?? [] as $message): ?>
        <?php $isError = $message['type'] === 'error'; ?>
        <div class="alert <?= $isError ? 'alert-danger' : 'alert-success' ?>" role="<?= $isError ? 'alert' : 'status' ?>">
            <?= escape($message['message']) ?>
        </div>
    <?php endforeach ?>
    <?= $content ?>
</main>
</body>
</html>
