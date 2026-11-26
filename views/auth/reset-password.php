<?php
/**
 * @var string $csrfToken
 * @var string $email
 * @var string|null $error
 */
?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Reset a password</h1>
                <p class="small text-body-secondary">Demo feature: anyone with the admin key can set a new password for any account.</p>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= escape($error) ?></div>
                <?php endif ?>
                <form method="post" action="/reset-password" data-lock-on-submit>
                    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="admin_key">Admin key</label>
                        <input class="form-control" id="admin_key" name="admin_key" type="password" autocomplete="off" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Account email</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?= escape($email) ?>" autocomplete="off" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Repeat the new password</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Set new password</button>
                </form>
                <p class="mt-3 mb-0 text-center small"><a href="/login">Back to sign in</a></p>
            </div>
        </div>
    </div>
</div>
