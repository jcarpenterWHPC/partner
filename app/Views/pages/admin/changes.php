<?php
/** @var list<array> $entries */
$label = static fn(string $f): string => $f === 'email_na' ? 'Email N/A' : ucfirst(str_replace('_', ' ', $f));
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div><h1 class="pp-h1">Recent roster changes</h1></div>
    
</div>
<div class="pp-tablewrap">
<table class="pp-table" id="changesTable">
    <thead><tr><th>When</th><th>Organization</th><th>Person</th><th>Change</th><th>By</th></tr></thead>
    <tbody>
    <?php if (!$entries): ?><tr><td colspan="5" class="pp-empty">No changes yet.</td></tr><?php endif; ?>
    <?php foreach ($entries as $e): $c = json_decode((string)$e['changes'], true) ?: []; ?>
        <tr data-action="<?= esc($e['action'], 'attr') ?>">
            <td title="<?= esc($e['created_at'], 'attr') ?>"><?= esc(nice_time($e['created_at'])) ?></td>
            <td><?= esc((string)$e['pmc_name']) ?></td>
            <td><a href="<?= base_url('roster/' . (int)$e['staff_id'] . '/history?pmc=' . (int)$e['pmc_id']) ?>"><?= esc((string)$e['staff_name']) ?></a></td>
            <td>
                <span class="pp-badge pp-badge--<?= esc($e['action'], 'attr') ?>"><?= esc(ucfirst($e['action'])) ?></span>
                <?php if ($e['action'] !== 'added' && $e['action'] !== 'imported'): ?><span class="pp-muted"><?= esc(implode(', ', array_map($label, array_keys($c)))) ?></span><?php endif; ?>
            </td>
            <td><?= esc($e['user_name'] ?: 'system') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= $this->endSection() ?>
