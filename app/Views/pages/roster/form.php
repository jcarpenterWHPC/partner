<?php
/**
 * @var array                       $pmc
 * @var array|null                  $person
 * @var array                       $v         field values (old input, or the person); `roles` number => role
 * @var array<string,list<string>>  $positions
 * @var array<string,string>        $names     property number => name
 * @var array<string,string>        $errors
 * @var string                      $back      'properties' when the Properties page sent us here
 */
$val = static fn(string $f): string => esc((string)($v[$f] ?? ''), 'attr');
$err = static fn(string $f): string => isset($errors[$f]) ? '<div class="pp-fielderror">' . esc($errors[$f]) . '</div>' : '';
$chosen = array_map('strval', $v['properties'] ?? []);
$roles = [];
foreach ((array)($v['roles'] ?? []) as $k => $r) {
    $roles[(string)$k] = (string)$r;
}
$position = (string)($v['position'] ?? '');
$emailNa = !empty($v['email_na']);
$cancel = base_url($back === 'properties' ? 'properties' : 'roster');
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1"><?= $person ? 'Edit ' . esc($person['name']) : 'Add a person' ?></h1>
        <p class="pp-sub"><?= esc($pmc['name']) ?></p>
    </div>

</div>
<?php if ($errors): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span>Check the highlighted fields.</span></div>
<?php endif; ?>
<?= view('partials/help', ['id' => 'person', 'title' => 'Filling this in', 'tips' => [
    '<strong>Position</strong> is their main job. Their role is the same at every property you tick unless you choose a different one beside that property.',
    '<strong>Start date</strong> is their first day. Leave <strong>end date</strong> empty until they leave; entering it is how you record a departure.',
    'Tick every property they cover; the filter box finds one quickly. Unticking a property hands it over: it shows as OPEN until someone else covers it.',
    'Tick <strong>N/A</strong> if they have no work email, so WHPC knows it is not missing.',
]]) ?>
<form method="post" action="<?= base_url($person ? 'roster/' . (int)$person['id'] : 'roster') ?>" class="pp-form pp-form--wide" id="personForm">
    <?= csrf_field() ?>
    <?php if ($back !== ''): ?><input type="hidden" name="back" value="<?= esc($back, 'attr') ?>"><?php endif; ?>
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
                    <option value="<?= esc($pos, 'attr') ?>" <?= $position === $pos ? 'selected' : '' ?>><?= esc($pos) ?></option>
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
            <input class="pp-input" id="email" name="email" type="email" value="<?= $val('email') ?>" maxlength="255" <?= $emailNa ? 'disabled' : '' ?>>
            <label class="pp-check pp-check--inline"><input type="checkbox" id="email_na" name="email_na" value="1" <?= $emailNa ? 'checked' : '' ?>> <span>N/A, this person has no work email</span></label>
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
            <span class="pp-label">Properties <span class="pp-muted">(and their role there, when it is not their position)</span></span>
            <?php if ($names === []): ?>
                <p class="pp-muted">No properties are listed for <?= esc($pmc['name']) ?> yet.</p>
            <?php else: ?>
            <input class="pp-input pp-input--filter" type="search" id="propFilter" placeholder="Filter…" aria-label="Filter properties">
            <div class="pp-checks" id="propChecks">
                <?php foreach ($names as $num => $label): $num = (string)$num; $on = in_array($num, $chosen, true); $role = $roles[$num] ?? ''; $differs = $on && $role !== '' && $role !== $position; ?>
                <label class="pp-check">
                    <input type="checkbox" name="properties[]" value="<?= esc($num, 'attr') ?>" <?= $on ? 'checked' : '' ?>>
                    <span><?= esc($label) ?> <span class="pp-muted"><?= esc($num) ?></span></span>
                    <select class="pp-input pp-input--select pp-role" name="roles[<?= esc($num, 'attr') ?>]" aria-label="Role at <?= esc($label, 'attr') ?>" <?= $on ? '' : 'hidden' ?>>
                        <option value="">Same as position</option>
                        <?php foreach ($positions as $group => $list): foreach ($list as $pos): ?>
                        <option value="<?= esc($pos, 'attr') ?>" <?= $differs && $role === $pos ? 'selected' : '' ?>><?= esc($pos) ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?= $err('properties') ?>
            <?= $err('roles') ?>
        </div>
        <div class="pp-field pp-field--4">
            <label class="pp-label" for="notes">Notes</label>
            <textarea class="pp-input" id="notes" name="notes" rows="3"><?= esc((string)($v['notes'] ?? '')) ?></textarea>
            <?= $err('notes') ?>
        </div>
    </div>
    <div class="pp-actions pp-actions--form">
        <button type="submit" class="pp-btn pp-btn--primary" id="savePerson"><?= $person ? 'Save changes' : 'Add to the roster' ?></button>
        <a class="pp-btn pp-btn--ghost" href="<?= $cancel ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    var f = document.getElementById('propFilter'), box = document.getElementById('propChecks');
    if (f && box) {
        f.addEventListener('input', function () {
            var q = f.value.trim().toLowerCase();
            box.querySelectorAll('.pp-check').forEach(function (l) {
                l.hidden = q !== '' && l.textContent.toLowerCase().indexOf(q) === -1 && !l.querySelector('input').checked;
            });
        });
        box.addEventListener('change', function (e) {
            if (e.target.type !== 'checkbox') { return; }
            var sel = e.target.parentNode.querySelector('.pp-role');
            if (sel) { sel.hidden = !e.target.checked; if (!e.target.checked) { sel.value = ''; } }
        });
    }
    var na = document.getElementById('email_na'), email = document.getElementById('email');
    if (na && email) {
        na.addEventListener('change', function () {
            email.disabled = na.checked;
            if (na.checked) { email.value = ''; }
        });
    }
})();
</script>
<?= $this->endSection() ?>
