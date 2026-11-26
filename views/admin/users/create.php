<?php
/**
 * @var string $csrfToken
 * @var string $email
 * @var string $role
 * @var list<\App\Domain\Enums\Role> $roles
 * @var App\Validation\ValidationResult $errors
 */
?>
<h1>Add a user</h1>
<p>There is no sign-up page: accounts are created here. Give the person their password in person.</p>
<form method="post" action="/admin/users" novalidate>
    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
    <p>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= escape($email) ?>" autocomplete="off" required>
        <?php if ($errors->error('email') !== null): ?>
            <span role="alert"><?= escape($errors->error('email')) ?></span>
        <?php endif ?>
    </p>
    <p>
        <label for="role">Role</label>
        <select id="role" name="role">
            <?php foreach ($roles as $option): ?>
                <option value="<?= escape($option->value) ?>"<?= $option->value === $role ? ' selected' : '' ?>><?= escape($option->value) ?></option>
            <?php endforeach ?>
        </select>
        <?php if ($errors->error('role') !== null): ?>
            <span role="alert"><?= escape($errors->error('role')) ?></span>
        <?php endif ?>
    </p>
    <p>
        <label for="password">Password (at least 12 characters)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
        <?php if ($errors->error('password') !== null): ?>
            <span role="alert"><?= escape($errors->error('password')) ?></span>
        <?php endif ?>
    </p>
    <p>
        <label for="password_confirmation">Repeat the password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
        <?php if ($errors->error('password_confirmation') !== null): ?>
            <span role="alert"><?= escape($errors->error('password_confirmation')) ?></span>
        <?php endif ?>
    </p>
    <button type="submit">Create account</button>
</form>
<p><a href="/admin/users">Back to users</a></p>
