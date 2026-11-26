<?php
/**
 * @var string $csrfToken
 * @var string $email
 * @var string|null $error
 */
?>
<h1>Reset a password</h1>
<p>Demo feature: anyone with the admin key can set a new password for any account.</p>
<?php if ($error !== null): ?>
    <p role="alert"><?= escape($error) ?></p>
<?php endif ?>
<form method="post" action="/reset-password">
    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
    <p>
        <label for="admin_key">Admin key</label>
        <input id="admin_key" name="admin_key" type="password" autocomplete="off" required autofocus>
    </p>
    <p>
        <label for="email">Account email</label>
        <input id="email" name="email" type="email" value="<?= escape($email) ?>" autocomplete="off" required>
    </p>
    <p>
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
    </p>
    <p>
        <label for="password_confirmation">Repeat the new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
    </p>
    <button type="submit">Set new password</button>
</form>
<p><a href="/login">Back to sign in</a></p>
