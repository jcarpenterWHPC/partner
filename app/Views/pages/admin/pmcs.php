<?php
/**
 * @var list<array>       $pmcs
 * @var array<int,string> $propCounts
 * @var array<int,string> $staffCounts
 * @var array<int,string> $userCounts
 * @var string|null       $lastSync
 */
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1">Management companies</h1>
        <p class="pp-sub"><?= $lastSync ? 'Properties synced ' . esc($lastSync) : 'Properties not synced yet' ?></p>
    </div>
    <div class="pp-actions">
        <form method="post" action="<?= base_url('admin/pmcs/sync') ?>"><?= csrf_field() ?><button type="submit" class="pp-btn pp-btn--primary" id="syncNow"><i class="bx bx-refresh"></i> Sync from Assets</button></form>
    </div>
</div>
<?php if (session()->getFlashdata('success')): ?>
    <div class="whpc-alert whpc-alert--success" role="status"><i class="bx bx-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
<?php endif; ?>
<div class="pp-tablewrap">
<table class="pp-table" id="pmcTable">
    <thead><tr><th>Company</th><th>Properties</th><th>Staff on roster</th><th>Portal users</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pmcs): ?><tr><td colspan="5" class="pp-empty">Nothing synced yet.</td></tr><?php endif; ?>
    <?php foreach ($pmcs as $p): ?>
        <tr data-id="<?= (int)$p['id'] ?>">
            <td><strong><?= esc($p['name']) ?></strong></td>
            <td><?= (int)($propCounts[$p['id']] ?? 0) ?></td>
            <td><?= (int)($staffCounts[$p['id']] ?? 0) ?></td>
            <td><?= (int)($userCounts[$p['id']] ?? 0) ?></td>
            <td class="pp-rowactions"><a href="<?= base_url('roster?pmc=' . (int)$p['id']) ?>">Roster <i class="bx bx-right-arrow-alt"></i></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= $this->endSection() ?>
