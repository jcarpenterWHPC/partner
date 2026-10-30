<?php
/**
 * @var list<array>          $users
 * @var list<array>          $pmcs
 * @var array<string,string> $errors
 * @var array<int,int>       $authored  user id => roster changes they made
 */
$err = static fn(string $f): string => isset($errors[$f]) ? '<div class="pp-fielderror">' . esc($errors[$f]) . '</div>' : '';
$accounts = new \App\Libraries\Accounts();
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div><h1 class="pp-h1">Portal users</h1></div>
    
</div>
<?php if (session()->getFlashdata('success')): ?>
    <div class="whpc-alert whpc-alert--success" role="status"><i class="bx bx-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
<?php endif; ?>
<form method="post" action="<?= base_url('admin/users') ?>" class="pp-form pp-form--wide pp-panel" id="userForm">
    <?= csrf_field() ?>
    <div class="pp-grid">
        <div class="pp-field">
            <label class="pp-label" for="u_name">Name</label>
            <input class="pp-input" id="u_name" name="name" value="<?= esc(old('name', ''), 'attr') ?>" maxlength="255" required>
            <?= $err('name') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="u_email">Work email</label>
            <input class="pp-input" id="u_email" name="email" type="email" value="<?= esc(old('email', ''), 'attr') ?>" maxlength="255" required>
            <?= $err('email') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="u_pmc">Organization</label>
            <select class="pp-input pp-input--select" id="u_pmc" name="pmc_id" required>
                <option value=""></option>
                <?php foreach ($pmcs as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (string)old('pmc_id', '') === (string)$p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('pmc_id') ?>
        </div>
    </div>
    <div class="pp-actions pp-actions--form">
        <button type="submit" class="pp-btn pp-btn--primary" id="addUser">Add account</button>
    </div>
</form>
<div class="pp-tablewrap">
<table class="pp-table" id="usersTable">
    <thead><tr><th>Name</th><th>Email</th><th>Organization</th><th>Role</th><th>Last sign-in</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr class="<?= (int)$u['is_active'] === 1 ? '' : 'is-disabled' ?>" data-id="<?= (int)$u['id'] ?>">
            <td><strong><?= esc($u['name']) ?></strong></td>
            <td><?= esc($u['email']) ?></td>
            <td><?= esc($u['pmc_name'] ?: 'WHPC') ?></td>
            <td><?= (int)$u['is_admin'] === 1 ? 'Admin' : 'Partner' ?></td>
            <td><?= esc((string)$u['last_login_at']) ?></td>
            <td class="pp-rowactions">
                <?php if ((int)$u['id'] !== (int)session('user')['id'] && !$accounts->isConfiguredAdmin((string)$u['email'])): ?>
                <a href="<?= base_url('admin/users/' . (int)$u['id'] . '/edit') ?>" aria-label="Edit <?= esc($u['name'], 'attr') ?>"><i class="bx bx-edit"></i></a>
                <form method="post" action="<?= base_url('admin/users/' . (int)$u['id'] . '/toggle') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="pp-btn pp-btn--ghost pp-btn--sm"><?= (int)$u['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
                </form>
                <?php if (empty($authored[(int)$u['id']])): ?>
                <form method="post" action="<?= base_url('admin/users/' . (int)$u['id'] . '/delete') ?>" onsubmit="return confirm('Delete <?= esc($u['email'], 'js') ?>?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="pp-btn pp-btn--ghost pp-btn--sm pp-btn--danger">Delete</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= $this->endSection() ?>
