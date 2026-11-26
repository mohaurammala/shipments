<?php
/**
 * @var list<App\Domain\UserSummary> $users
 * @var list<\App\Domain\Enums\Role> $roles
 * @var App\Domain\User $currentUser
 * @var string $csrfToken
 */
?>
<h1>Users</h1>
<p><a href="/admin/users/new">Add a user</a></p>
<table>
    <thead>
    <tr>
        <th scope="col">Email</th>
        <th scope="col">Role</th>
        <th scope="col">Status</th>
        <th scope="col">Last signed in (UTC)</th>
        <th scope="col">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $summary): ?>
        <?php $user = $summary->user; ?>
        <tr>
            <td>
                <?= escape($user->email) ?>
                <?php if ($user->id === $currentUser->id): ?>(you)<?php endif ?>
            </td>
            <td>
                <form method="post" action="/admin/users/<?= escape($user->id) ?>/role">
                    <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                    <label class="visually-hidden" for="role-<?= escape($user->id) ?>">Role for <?= escape($user->email) ?></label>
                    <select id="role-<?= escape($user->id) ?>" name="role">
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= escape($role->value) ?>"<?= $role === $user->role ? ' selected' : '' ?>><?= escape($role->value) ?></option>
                        <?php endforeach ?>
                    </select>
                    <button type="submit">Change role</button>
                </form>
            </td>
            <td><?= $user->isActive ? 'Active' : 'Deactivated' ?></td>
            <td><?= escape($summary->lastSignInAt?->format('Y-m-d H:i') ?? 'Never') ?></td>
            <td>
                <?php if ($user->id === $currentUser->id): ?>
                    &mdash;
                <?php elseif ($user->isActive): ?>
                    <form method="post" action="/admin/users/<?= escape($user->id) ?>/deactivate">
                        <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                        <button type="submit">Deactivate</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/admin/users/<?= escape($user->id) ?>/reactivate">
                        <input type="hidden" name="_token" value="<?= escape($csrfToken) ?>">
                        <button type="submit">Reactivate</button>
                    </form>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
