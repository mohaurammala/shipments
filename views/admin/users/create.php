<?php
/**
 * @var string $csrfToken
 * @var string $email
 * @var string $role
 * @var list<\App\Domain\Enums\Role> $roles
 * @var App\Validation\ValidationResult $errors
 */

$invalid = static fn (string $field): string => $errors->error($field) === null ? '' : ' is-invalid';
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4">Add a user</h1>
                <p class="small text-body-secondary">There is no sign-up page: accounts are created here. Give the person their password in person.</p>
                <form method="post" action="/admin/users" novalidate data-lock-on-submit>
                    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control<?= $invalid('email') ?>" id="email" name="email" type="email" value="<?= escape($email) ?>" autocomplete="off" required>
                        <?php if ($errors->error('email') !== null): ?>
                            <div class="invalid-feedback" role="alert"><?= escape($errors->error('email')) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select<?= $invalid('role') ?>" id="role" name="role">
                            <?php foreach ($roles as $option): ?>
                                <option value="<?= escape($option->value) ?>"<?= $option->value === $role ? ' selected' : '' ?>><?= escape($option->value) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?php if ($errors->error('role') !== null): ?>
                            <div class="invalid-feedback" role="alert"><?= escape($errors->error('role')) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password (at least 12 characters)</label>
                        <input class="form-control<?= $invalid('password') ?>" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                        <?php if ($errors->error('password') !== null): ?>
                            <div class="invalid-feedback" role="alert"><?= escape($errors->error('password')) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Repeat the password</label>
                        <input class="form-control<?= $invalid('password_confirmation') ?>" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                        <?php if ($errors->error('password_confirmation') !== null): ?>
                            <div class="invalid-feedback" role="alert"><?= escape($errors->error('password_confirmation')) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create account</button>
                        <a class="btn btn-link" href="/admin/users">Back to users</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
