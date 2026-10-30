<?php
/**
 * @var array        $pmc
 * @var list<array>  $pmcs      every organisation (admins only)
 * @var list<array>  $rows      property, cells (role => people + vacancy), others, attention
 * @var list<string> $expected  the role columns
 * @var int          $attention positions with nobody and no coverage note
 */
$services = \App\Models\PmcModel::isServices($pmc);
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1">Properties</h1>
        <?php if ($pmcs): ?>
        <form method="get" action="<?= base_url('properties') ?>" class="pp-form--inline pp-pmcpick">
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
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('properties/export') ?>" id="exportCsv"><i class="bx bx-download"></i> Download</a>
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('roster') ?>" id="toRoster"><i class="bx bx-id-card"></i> By person</a>
        <a class="pp-btn pp-btn--primary" href="<?= base_url('roster/new?back=properties') ?>" id="addPerson"><i class="bx bx-plus"></i> Add a person</a>
    </div>
</div>
<?php if (session()->getFlashdata('success')): ?>
    <div class="whpc-alert whpc-alert--success" role="status"><i class="bx bx-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
<?php endif; ?>
<?= view('partials/help', ['id' => 'properties', 'title' => 'How this works', 'tips' => [
    'Each column is a role every property should have filled: ' . esc(implode(', ', $expected)) . '. A name means it is filled; <span class="pp-open">OPEN</span> means nobody holds it. Red means OPEN with no note on who is covering.',
    '<strong>Someone left:</strong> click their name and enter their end date. <strong>Someone new:</strong> <strong>Add a person</strong> and tick the property; the OPEN position closes itself.',
    '<strong>Record vacancy</strong> / <strong>Update</strong>: say since when the position is open and who is looking after the property meanwhile.',
    '<strong>Also</strong> lists people at the property in other roles. <strong>Download</strong> gives you the current assignments as a spreadsheet.',
]]) ?>
<div class="pp-toolbar">
    <input class="pp-input pp-input--filter" type="search" id="boardFilter" placeholder="Find a property…" aria-label="Find a property">
    <label class="pp-check pp-check--inline"><input type="checkbox" id="attnOnly"> <span>Needs attention only (<?= (int)$attention ?>)</span></label>
</div>
<div class="pp-tablewrap">
<table class="pp-table pp-board" id="boardTable">
    <thead>
        <tr>
            <th>Property</th>
            <?php foreach ($expected as $role): ?><th><?= esc($role) ?></th><?php endforeach; ?>
            <th>Also</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="<?= count($expected) + 2 ?>" class="pp-empty"><?= $services ? 'No properties yet. Add a person and the properties they cover.' : 'No properties are listed yet.' ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): $p = $r['property']; $pn = (string)$p['property_number']; ?>
        <tr class="<?= $r['attention'] > 0 ? 'has-attn' : '' ?>" data-attn="<?= (int)$r['attention'] ?>" data-pn="<?= esc($pn, 'attr') ?>">
            <td>
                <strong><?= esc($p['name']) ?></strong>
                <span class="pp-cell__sub"><?= esc($pn) ?><?= $p['city'] !== null && $p['city'] !== '' ? ' · ' . esc($p['city']) : '' ?><?= $services && (string)$p['management_co'] !== '' ? ' · ' . esc($p['management_co']) : '' ?></span>
            </td>
            <?php foreach ($expected as $role): $cell = $r['cells'][$role]; $v = $cell['vacancy']; ?>
            <td>
                <?php if ($cell['people'] !== []): ?>
                    <?php foreach ($cell['people'] as $s): ?>
                    <a class="pp-cell__name" href="<?= base_url('roster/' . (int)$s['id'] . '/edit?back=properties') ?>"><?= esc($s['name']) ?></a>
                    <?php if ((int)$s['email_na'] === 1): ?><span class="pp-cell__sub pp-na">No email (N/A)</span>
                    <?php elseif ($s['email'] !== ''): ?><span class="pp-cell__sub"><?= esc($s['email']) ?></span><?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php $attn = $v === null || trim((string)$v['coverage']) === ''; ?>
                    <span class="pp-open <?= $attn ? 'pp-open--attn' : '' ?>">OPEN<?= $v !== null && $v['open_since'] ? ' since ' . esc($v['open_since']) : '' ?></span>
                    <?php if ($v !== null && trim((string)$v['coverage']) !== ''): ?><span class="pp-cell__sub">Covered by <?= esc($v['coverage']) ?></span><?php endif; ?>
                    <a class="pp-cell__act" href="<?= base_url('properties/open?' . http_build_query(['property' => $pn, 'role' => $role])) ?>"><?= $v === null ? 'Record vacancy' : 'Update' ?></a>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td><?= $r['others'] === [] ? '<span class="pp-muted">—</span>' : esc(implode(', ', $r['others'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    var f = document.getElementById('boardFilter'), a = document.getElementById('attnOnly'), rows = document.querySelectorAll('#boardTable tbody tr[data-pn]');
    function apply() {
        var q = f.value.trim().toLowerCase(), only = a.checked;
        rows.forEach(function (tr) {
            tr.hidden = (q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1) || (only && tr.getAttribute('data-attn') === '0');
        });
    }
    if (f && a) { f.addEventListener('input', apply); a.addEventListener('change', apply); }
})();
</script>
<?= $this->endSection() ?>
