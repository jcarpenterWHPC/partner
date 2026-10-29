<?php
/**
 * @var array                       $pmc
 * @var array|null                  $person
 * @var array                       $v         field values (old input, or the person)
 * @var array<string,list<string>>  $positions
 * @var array<string,string>        $names     property number => name
 * @var array<string,string>        $errors
 */
$val = static fn(string $f): string => esc((string)($v[$f] ?? ''), 'attr');
$err = static fn(string $f): string => isset($errors[$f]) ? '<div class="pp-fielderror">' . esc($errors[$f]) . '</div>' : '';
$chosen = array_map('strval', $v['properties'] ?? []);
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1"><?= $person ? 'Edit ' . esc($person['name']) : 'Add a person' ?></h1>
        <p class="pp-sub"><?= esc($pmc['name']) ?></p>
    </div>
    <div class="pp-actions"><a class="pp-btn pp-btn--ghost" href="<?= base_url('roster') ?>">Back to the roster</a></div>
</div>
<?php if ($errors): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span>Check the highlighted fields.</span></div>
<?php endif; ?>
<form method="post" action="<?= base_url($person ? 'roster/' . (int)$person['id'] : 'roster') ?>" class="pp-form pp-form--wide" id="personForm">
    <?= csrf_field() ?>
    <div class="pp-grid">
        <div class="pp-field pp-field--2">
            <label class="pp-label" for="name">Name</label>
            <input class="pp-input" id="name" name="name" value="<?= $val('name') ?>" maxlength="255" required autofocus>
            <?= $err('name') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="position">Position</label>
            <select class="pp-input pp-input--select" id="position" name="position" required>
                <option value="">Choose…</option>
                <?php foreach ($positions as $group => $list): ?>
                <optgroup label="<?= esc($group, 'attr') ?>">
                    <?php foreach ($list as $pos): ?>
                    <option value="<?= esc($pos, 'attr') ?>" <?= ($v['position'] ?? '') === $pos ? 'selected' : '' ?>><?= esc($pos) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
            <?= $err('position') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="title">Title <span class="pp-muted">(as on their card)</span></label>
            <input class="pp-input" id="title" name="title" value="<?= $val('title') ?>" maxlength="255">
            <?= $err('title') ?>
        </div>
        <div class="pp-field pp-field--2">
            <label class="pp-label" for="email">Work email</label>
            <input class="pp-input" id="email" name="email" type="email" value="<?= $val('email') ?>" maxlength="255">
            <?= $err('email') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="mobile_phone">Mobile</label>
            <input class="pp-input" id="mobile_phone" name="mobile_phone" type="tel" value="<?= $val('mobile_phone') ?>" maxlength="40">
            <?= $err('mobile_phone') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="office_phone">Office</label>
            <input class="pp-input" id="office_phone" name="office_phone" type="tel" value="<?= $val('office_phone') ?>" maxlength="40">
            <?= $err('office_phone') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="start_date">Start date</label>
            <input class="pp-input" id="start_date" name="start_date" type="date" value="<?= $val('start_date') ?>">
            <?= $err('start_date') ?>
        </div>
        <div class="pp-field">
            <label class="pp-label" for="end_date">End date <span class="pp-muted">(when they leave)</span></label>
            <input class="pp-input" id="end_date" name="end_date" type="date" value="<?= $val('end_date') ?>">
            <?= $err('end_date') ?>
        </div>
        <div class="pp-field pp-field--4">
            <span class="pp-label">Properties</span>
            <?php if ($names === []): ?>
                <p class="pp-muted">No properties are listed for <?= esc($pmc['name']) ?> yet.</p>
            <?php else: ?>
            <input class="pp-input pp-input--filter" type="search" id="propFilter" placeholder="Filter…" aria-label="Filter properties">
            <div class="pp-checks" id="propChecks">
                <?php foreach ($names as $num => $label): ?>
                <label class="pp-check"><input type="checkbox" name="properties[]" value="<?= esc((string)$num, 'attr') ?>" <?= in_array((string)$num, $chosen, true) ? 'checked' : '' ?>> <span><?= esc($label) ?> <span class="pp-muted"><?= esc((string)$num) ?></span></span></label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?= $err('properties') ?>
        </div>
        <div class="pp-field pp-field--4">
            <label class="pp-label" for="notes">Notes</label>
            <textarea class="pp-input" id="notes" name="notes" rows="3"><?= esc((string)($v['notes'] ?? '')) ?></textarea>
            <?= $err('notes') ?>
        </div>
    </div>
    <div class="pp-actions pp-actions--form">
        <button type="submit" class="pp-btn pp-btn--primary" id="savePerson"><?= $person ? 'Save changes' : 'Add to the roster' ?></button>
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('roster') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    var f = document.getElementById('propFilter'), box = document.getElementById('propChecks');
    if (!f || !box) { return; }
    f.addEventListener('input', function () {
        var q = f.value.trim().toLowerCase();
        box.querySelectorAll('.pp-check').forEach(function (l) {
            l.hidden = q !== '' && l.textContent.toLowerCase().indexOf(q) === -1 && !l.querySelector('input').checked;
        });
    });
})();
</script>
<?= $this->endSection() ?>
