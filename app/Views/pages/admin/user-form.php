<?php
/**
 * @var array                $user
 * @var list<array>          $pmcs
 * @var array                $v       field values (old input, or the account)
 * @var array<string,string> $errors
 */
$err = static fn(string $f): string => isset($errors[$f]) ? '<div class="pp-fielderror">' . esc($errors[$f]) . '</div>' : '';
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div><h1 class="pp-h1">Edit <?= esc($user['name']) ?></h1></div>
    
</div>
<?php if ($errors): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span>Check the highlighted fields.</span></div>
<?php endif; ?>
<form method="post" action="<?= base_url('admin/users/' . (int)$user['id']) ?>" class="pp-form pp-form--wide pp-panel" id="userForm">
    <?= csrf_field() ?>
    <div class="pp-grid">
        <div class="pp-field">
            <label class="pp-label" for="u_name">Name</label>
            <input class="pp-input" id="u_name" name="name" value="<?= esc((string)$v['name'], 'attr') ?>" maxlength="255" required>
            <?= $err('name') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="u_email">Work email</label>
            <input class="pp-input" id="u_email" name="email" type="email" value="<?= esc((string)$v['email'], 'attr') ?>" maxlength="255" required>
            <?= $err('email') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="u_pmc">Organization</label>
            <select class="pp-input pp-input--select" id="u_pmc" name="pmc_id" <?= (int)$user['is_admin'] === 1 ? '' : 'required' ?>>
                <option value=""></option>
                <?php foreach ($pmcs as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (string)($v['pmc_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('pmc_id') ?>
        </div>
    </div>
    <div class="pp-actions pp-actions--form">
        <button type="submit" class="pp-btn pp-btn--primary" id="saveUser">Save</button>
    </div>
</form>
<?= $this->endSection() ?>
