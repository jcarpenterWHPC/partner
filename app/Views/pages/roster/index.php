<?php
/**
 * @var array        $pmc
 * @var list<array>  $pmcs      every organisation (admins only; empty for a partner user)
 * @var list<array>  $people
 * @var array<string,string> $names property number => name
 * @var array<string,list<string>> $positions
 * @var array<string,array{people:int,open:int}> $summary by position, in config order
 * @var bool         $showDeparted
 */
$current = array_filter($people, static fn(array $p): bool => $p['end_date'] === null);
$departed = array_filter($people, static fn(array $p): bool => $p['end_date'] !== null);
$rows = $showDeparted ? $people : $current;
$totalOpen = array_sum(array_column($summary, 'open'));
$usedProps = [];
foreach ($rows as $p) {
    foreach ($p['properties'] as $n) {
        $usedProps[$n] = $names[$n] ?? $n;
    }
}
asort($usedProps);
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
        <a class="pp-btn pp-btn--primary" href="<?= base_url('roster/new') ?>" id="addPerson"><i class="bx bx-plus"></i> Add a person</a>
    </div>
</div>
<?php if (session()->getFlashdata('success')): ?>
    <div class="whpc-alert whpc-alert--success" role="status"><i class="bx bx-check-circle"></i><span><?= esc(session()->getFlashdata('success')) ?></span></div>
<?php endif; ?>
<?= view('partials/help', ['id' => 'roster', 'title' => 'How this works', 'tips' => [
    'One row per person, however many properties they cover. What WHPC had on file is already here.',
    '<strong>Someone left:</strong> click their edit icon and enter the <strong>end date</strong> (their last day). They move to the departed list and their positions show as OPEN on the <a href="' . base_url('properties') . '">Properties</a> page.',
    '<strong>Someone new:</strong> <strong>Add a person</strong>, choose their position, tick the properties they cover. If their role at one property differs from their position, pick it beside that property.',
    'No work email? Tick <strong>N/A</strong> on their form. Nothing is ever deleted; the history icon shows every change with its date.',
]]) ?>
<div class="pp-stats" id="rosterSummary">
    <button type="button" class="pp-stat is-active" data-position="">
        <span class="pp-stat__n"><?= count($current) ?></span>
        <span class="pp-stat__label">People</span>
    </button>
    <?php foreach ($summary as $pos => $c): ?>
    <button type="button" class="pp-stat" data-position="<?= esc($pos, 'attr') ?>">
        <span class="pp-stat__n"><?= (int)$c['people'] ?></span>
        <span class="pp-stat__label"><?= esc($pos) ?></span>
        <?php if ($c['open'] > 0): ?><span class="pp-stat__open"><?= (int)$c['open'] ?> open</span><?php endif; ?>
    </button>
    <?php endforeach; ?>
    <a class="pp-stat pp-stat--open" href="<?= base_url('properties') ?>" id="openPositions">
        <span class="pp-stat__n"><?= (int)$totalOpen ?></span>
        <span class="pp-stat__label">Open positions</span>
    </a>
</div>
<div class="pp-toolbar">
    <input class="pp-input pp-input--filter" type="search" id="rosterSearch" placeholder="Search name, email, phone, property…" aria-label="Search the roster">
    <select class="pp-input pp-input--select" id="positionFilter" aria-label="Position">
        <option value="">All positions</option>
        <?php foreach ($positions as $group => $list): ?>
        <optgroup label="<?= esc($group, 'attr') ?>">
            <?php foreach ($list as $pos): ?><option value="<?= esc($pos, 'attr') ?>"><?= esc($pos) ?></option><?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
    </select>
    <select class="pp-input pp-input--select" id="propertyFilter" aria-label="Property">
        <option value="">All properties</option>
        <?php foreach ($usedProps as $num => $label): ?><option value="<?= esc((string)$num, 'attr') ?>"><?= esc($label) ?></option><?php endforeach; ?>
    </select>
    <label class="pp-check pp-check--inline">
        <input type="checkbox" id="toggleDeparted" <?= $showDeparted ? '' : 'checked' ?> data-hide="<?= base_url('roster') ?>" data-show="<?= base_url('roster?departed=1') ?>">
        <span>Hide departed employees (<?= count($departed) ?>)</span>
    </label>
    <span class="pp-muted" id="rosterCount"></span>
</div>
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
        <tr id="rosterNoMatch" hidden><td colspan="6" class="pp-empty">Nobody matches. Clear the search or filters above.</td></tr>
    <?php foreach ($rows as $p): ?>
        <tr class="<?= $p['end_date'] !== null ? 'is-departed' : '' ?>" data-id="<?= (int)$p['id'] ?>" data-position="<?= esc($p['position'], 'attr') ?>" data-roles="<?= esc(implode('|', array_unique(array_merge([$p['position']], array_values($p['roles'])))), 'attr') ?>" data-props="|<?= esc(implode('|', $p['properties']), 'attr') ?>|" data-text="<?= esc(mb_strtolower(implode(' ', [$p['name'], $p['title'], $p['email'], $p['mobile_phone'], $p['office_phone'], $p['position'], $propList($p)])), 'attr') ?>">
            <td>
                <strong><?= esc($p['name']) ?></strong>
                <?php if ($p['end_date'] !== null): ?><span class="pp-badge pp-badge--departed">Departed <?= esc(nice_date($p['end_date'])) ?></span><?php endif; ?>
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
            <td><?= esc(nice_date($p['start_date'])) ?></td>
            <td class="pp-rowactions">
                <a class="pp-btn pp-btn--ghost pp-btn--sm" href="<?= base_url('roster/' . (int)$p['id'] . '/history') ?>" aria-label="View <?= esc($p['name'], 'attr') ?>"><i class="bx bx-show"></i> View</a>
                <a class="pp-btn pp-btn--ghost pp-btn--sm" href="<?= base_url('roster/' . (int)$p['id'] . '/edit') ?>" aria-label="Edit <?= esc($p['name'], 'attr') ?>"><i class="bx bx-edit"></i> Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    var q = document.getElementById('rosterSearch'), pos = document.getElementById('positionFilter'), prop = document.getElementById('propertyFilter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#rosterTable tbody tr[data-id]'));
    var none = document.getElementById('rosterNoMatch'), count = document.getElementById('rosterCount');
    var tiles = document.querySelectorAll('#rosterSummary .pp-stat[data-position]');
    function apply() {
        var text = q.value.trim().toLowerCase(), p = pos.value, pr = prop.value, shown = 0;
        rows.forEach(function (tr) {
            var ok = (text === '' || tr.getAttribute('data-text').indexOf(text) !== -1)
                && (p === '' || ('|' + tr.getAttribute('data-roles') + '|').indexOf('|' + p + '|') !== -1)
                && (pr === '' || tr.getAttribute('data-props').indexOf('|' + pr + '|') !== -1);
            tr.hidden = !ok;
            if (ok) { shown++; }
        });
        none.hidden = shown > 0 || rows.length === 0;
        count.textContent = shown === rows.length ? '' : shown + ' of ' + rows.length;
        tiles.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-position') === p); });
    }
    q.addEventListener('input', apply);
    pos.addEventListener('change', apply);
    prop.addEventListener('change', apply);
    tiles.forEach(function (t) {
        t.addEventListener('click', function () {
            var v = t.getAttribute('data-position');
            pos.value = pos.value === v ? '' : v;
            apply();
        });
    });
    var dep = document.getElementById('toggleDeparted');
    dep.addEventListener('change', function () { location.href = dep.checked ? dep.getAttribute('data-hide') : dep.getAttribute('data-show'); });
    apply();
})();
</script>
<?= $this->endSection() ?>
