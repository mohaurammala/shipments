<?php
/**
 * @var list<App\Domain\UserSummary> $users
 * @var list<\App\Domain\Enums\Role> $roles
 * @var App\Domain\User $currentUser
 * @var string $csrfToken
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Users</h1>
    <a class="btn btn-primary" href="/admin/users/new">Add a user</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Email</th>
                <th scope="col">Role</th>
                <th scope="col">Status</th>
                <th scope="col">Last signed in</th>
                <th scope="col">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $summary): ?>
                <?php $user = $summary->user; ?>
                <tr>
                    <td>
                        <?= escape($user->email) ?>
                        <?php if ($user->id === $currentUser->id): ?><span class="badge text-bg-light border">you</span><?php endif ?>
                    </td>
                    <td>
                        <form method="post" action="/admin/users/<?= escape($user->id) ?>/role" class="d-flex gap-2">
                            <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                            <label class="visually-hidden" for="role-<?= escape($user->id) ?>">Role for <?= escape($user->email) ?></label>
                            <select class="form-select form-select-sm w-auto" id="role-<?= escape($user->id) ?>" name="role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= escape($role->value) ?>"<?= $role === $user->role ? ' selected' : '' ?>><?= escape($role->value) ?></option>
                                <?php endforeach ?>
                            </select>
                            <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">Change role</button>
                        </form>
                    </td>
                    <td>
                        <span class="badge <?= $user->isActive ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $user->isActive ? 'Active' : 'Deactivated' ?></span>
                    </td>
                    <td class="text-nowrap"><?= escape($summary->lastSignInAt === null ? 'Never' : local_time($summary->lastSignInAt, 'Y-m-d H:i')) ?></td>
                    <td>
                        <?php if ($user->id === $currentUser->id): ?>
                            &mdash;
                        <?php elseif ($user->isActive): ?>
                            <form method="post" action="/admin/users/<?= escape($user->id) ?>/deactivate">
                                <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/admin/users/<?= escape($user->id) ?>/reactivate">
                                <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                                <button type="submit" class="btn btn-outline-success btn-sm">Reactivate</button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
