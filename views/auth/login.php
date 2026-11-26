<?php
/**
 * @var string $csrfToken
 * @var string $email
 * @var string|null $error
 * @var string|null $notice
 * @var bool $passwordResetEnabled
 */
?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Sign in</h1>
                <?php if ($notice !== null): ?>
                    <div class="alert alert-success" role="status"><?= escape($notice) ?></div>
                <?php endif ?>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?= escape($error) ?></div>
                <?php endif ?>
                <form method="post" action="/login" data-lock-on-submit>
                    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?= escape($email) ?>" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Sign in</button>
                </form>
                <?php if ($passwordResetEnabled): ?>
                    <p class="mt-3 mb-0 text-center small"><a href="/reset-password">Forgotten your password?</a></p>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
