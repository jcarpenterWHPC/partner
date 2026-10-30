<?php
/**
 * @var array        $pmc
 * @var list<array>  $pmcs    every organisation (admins only; empty for a partner user)
 * @var list<array>  $people
 * @var array<string,string> $names property number => name
 * @var bool         $showDeparted
 */
$current = array_filter($people, static fn(array $p): bool => $p['end_date'] === null);
$departed = array_filter($people, static fn(array $p): bool => $p['end_date'] !== null);
$rows = $showDeparted ? $people : $current;
$propList = static function (array $p) use ($names): string {
    $n = [];
    foreach ($p['roles'] as $k => $role) {
        $n[] = ($names[(string)$k] ?? (string)$k) . ($role !== '' && $role !== $p['position'] ? ' · ' . $role : '');
    }
    sort($n);
    return implode(', ', $n);
};
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1">Staff roster</h1>
        <?php if ($pmcs): ?>
        <form method="get" action="<?= base_url('roster') ?>" class="pp-form--inline pp-pmcpick">
            <label class="pp-label" for="pmcPick">Organization</label>
            <select class="pp-input pp-input--select" id="pmcPick" name="pmc" onchange="this.form.submit()">
                <?php foreach ($pmcs as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$pmc['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php else: ?>
        <p class="pp-sub"><?= esc($pmc['name']) ?></p>
        <?php endif; ?>
    </div>
    <div class="pp-actions">
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('properties') ?>" id="toProperties"><i class="bx bx-building-house"></i> By property</a>
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('roster?' . http_build_query(['departed' => $showDeparted ? null : 1])) ?>" id="toggleDeparted">
            <?= $showDeparted ? 'Hide departed' : 'Show departed (' . count($departed) . ')' ?>
        </a>
        <a class="pp-btn pp-btn--primary" href="<?= base_url('roster/new') ?>" id="addPerson"><i class="bx bx-plus"></i> Add a person</a>
    </div>
</div>
<?php if (session()->getFlashdata('success')): ?>
    <div class="whpc-alert whpc-alert--success" role="status"><i class="bx bx-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
<?php endif; ?>
<div class="pp-tablewrap">
<table class="pp-table" id="rosterTable">
    <thead>
        <tr>
            <th>Name</th>
            <th>Position</th>
            <th>Contact</th>
            <th>Properties</th>
            <th>Since</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="6" class="pp-empty">No one on the roster yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $p): ?>
        <tr class="<?= $p['end_date'] !== null ? 'is-departed' : '' ?>" data-id="<?= (int)$p['id'] ?>">
            <td>
                <strong><?= esc($p['name']) ?></strong>
                <?php if ($p['end_date'] !== null): ?><span class="pp-badge pp-badge--departed">Departed <?= esc($p['end_date']) ?></span><?php endif; ?>
            </td>
            <td><?= esc($p['position']) ?><?= $p['title'] !== '' ? '<br><span class="pp-muted">' . esc($p['title']) . '</span>' : '' ?></td>
            <td>
                <?php if ((int)$p['email_na'] === 1): ?><span class="pp-na">No email (N/A)</span><br>
                <?php elseif ($p['email'] !== ''): ?><a href="mailto:<?= esc($p['email'], 'attr') ?>"><?= esc($p['email']) ?></a><br><?php endif; ?>
                <?php if ($p['mobile_phone'] !== ''): ?><span class="pp-muted">M</span> <?= esc($p['mobile_phone']) ?><br><?php endif; ?>
                <?php if ($p['office_phone'] !== ''): ?><span class="pp-muted">O</span> <?= esc($p['office_phone']) ?><?php endif; ?>
            </td>
            <td class="pp-props" title="<?= esc($propList($p), 'attr') ?>">
                <?php $n = count($p['properties']); ?>
                <?= $n === 0 ? '<span class="pp-muted">—</span>' : esc($n === 1 ? $propList($p) : $n . ' properties') ?>
            </td>
            <td><?= esc((string)$p['start_date']) ?></td>
            <td class="pp-rowactions">
                <a href="<?= base_url('roster/' . (int)$p['id'] . '/edit') ?>" aria-label="Edit <?= esc($p['name'], 'attr') ?>"><i class="bx bx-edit"></i></a>
                <a href="<?= base_url('roster/' . (int)$p['id'] . '/history') ?>" aria-label="History of <?= esc($p['name'], 'attr') ?>"><i class="bx bx-history"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= $this->endSection() ?>
