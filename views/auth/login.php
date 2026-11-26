<?php
/**
 * @var string $csrfToken
 * @var string $email
 * @var string|null $error
 * @var string|null $notice
 * @var bool $passwordResetEnabled
 */
?>
<h1>Sign in</h1>
<?php if ($notice !== null): ?>
    <p role="status"><?= escape($notice) ?></p>
<?php endif ?>
<?php if ($error !== null): ?>
    <p role="alert"><?= escape($error) ?></p>
<?php endif ?>
<form method="post" action="/login">
    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
    <p>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= escape($email) ?>" autocomplete="username" required autofocus>
    </p>
    <p>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
    </p>
    <button type="submit">Sign in</button>
</form>
<?php if ($passwordResetEnabled): ?>
    <p><a href="/reset-password">Forgotten your password?</a></p>
<?php endif ?>
