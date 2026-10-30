<?php
/**
 * @var list<array>          $pmcs
 * @var array<string,string> $kinds
 * @var array<int,string>    $propCounts
 * @var array<int,string>    $staffCounts
 * @var array<int,string>    $userCounts
 * @var array<int,string>    $openCounts
 * @var string|null          $lastSync
 * @var array<string,string> $errors
 */
$err = static fn(string $f): string => isset($errors[$f]) ? '<div class="pp-fielderror">' . esc($errors[$f]) . '</div>' : '';
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1">Organizations</h1>
        <p class="pp-sub"><?= $lastSync ? 'Synced from Assets ' . esc($lastSync) : 'Not synced from Assets yet' ?></p>
    </div>
    <div class="pp-actions">
        <form method="post" action="<?= base_url('admin/pmcs/sync') ?>"><?= csrf_field() ?><button type="submit" class="pp-btn pp-btn--primary" id="syncNow"><i class="bx bx-refresh"></i> Sync from Assets</button></form>
    </div>
</div>
<?php if (session()->getFlashdata('success')): ?>
    <div class="whpc-alert whpc-alert--success" role="status"><i class="bx bx-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
<?php endif; ?>
<div class="pp-tablewrap">
<table class="pp-table" id="pmcTable">
    <thead><tr><th>Organization</th><th>Kind</th><th>Properties</th><th>Staff on roster</th><th>Open positions</th><th>Portal users</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pmcs): ?><tr><td colspan="7" class="pp-empty">Nothing synced yet.</td></tr><?php endif; ?>
    <?php foreach ($pmcs as $p): ?>
        <tr data-id="<?= (int)$p['id'] ?>">
            <td><strong><?= esc($p['name']) ?></strong></td>
            <td><span class="pp-kind"><?= esc($kinds[$p['kind'] ?? 'management'] ?? $p['kind']) ?></span></td>
            <td><?= (int)($propCounts[$p['id']] ?? 0) ?></td>
            <td><?= (int)($staffCounts[$p['id']] ?? 0) ?></td>
            <td><?= (int)($openCounts[$p['id']] ?? 0) ?></td>
            <td><?= (int)($userCounts[$p['id']] ?? 0) ?></td>
            <td class="pp-rowactions"><a href="<?= base_url('properties?pmc=' . (int)$p['id']) ?>">Properties</a> · <a href="<?= base_url('roster?pmc=' . (int)$p['id']) ?>">Roster <i class="bx bx-right-arrow-alt"></i></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<form method="post" action="<?= base_url('admin/pmcs') ?>" class="pp-form pp-form--wide pp-panel" id="orgForm">
    <?= csrf_field() ?>
    <div class="pp-grid">
        <div class="pp-field pp-field--2">
            <label class="pp-label" for="o_name">Add an organization Assets does not list as a management company</label>
            <input class="pp-input" id="o_name" name="name" value="<?= esc(old('name', ''), 'attr') ?>" maxlength="255" placeholder="Name, exactly as Assets spells the company" required>
            <?= $err('name') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="o_kind">Kind</label>
            <select class="pp-input pp-input--select" id="o_kind" name="kind" required>
                <?php foreach ($kinds as $k => $label): ?>
                <option value="<?= esc($k, 'attr') ?>" <?= old('kind', 'services') === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('kind') ?>
        </div>
        <div class="pp-field pp-field--check">
            <button type="submit" class="pp-btn pp-btn--primary" id="addOrg">Add</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
