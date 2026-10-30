<?php
/**
 * @var array                $pmc
 * @var array                $property
 * @var string               $role
 * @var list<array>          $holders   people currently in the role here (empty when open)
 * @var array                $v         open_since, coverage (old input or the vacancy)
 * @var array|null           $vacancy   the open vacancy on file, if any
 * @var array<string,string> $errors
 */
$err = static fn(string $f): string => isset($errors[$f]) ? '<div class="pp-fielderror">' . esc($errors[$f]) . '</div>' : '';
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1"><?= esc($role) ?> · <?= esc($property['name']) ?></h1>
        <p class="pp-sub"><?= esc((string)$property['property_number']) ?><?= $property['city'] ? ' · ' . esc($property['city']) : '' ?> · <?= esc($pmc['name']) ?></p>
    </div>

</div>
<?php if ($holders): ?>
    <div class="pp-panel">
        <p>Held by
        <?php foreach ($holders as $i => $s): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= base_url('roster/' . (int)$s['id'] . '/edit?back=properties') ?>"><?= esc($s['name']) ?></a><?php endforeach; ?>.
        To record a departure, edit the person: give them an end date, or untick this property if they stay on elsewhere. The position then shows as open here.</p>
    </div>
<?php else: ?>
<?php if ($errors): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span>Check the highlighted fields.</span></div>
<?php endif; ?>
<p class="pp-intro">Nobody holds this position. Record when it went open and who is looking after the property until it is filled. When you add the replacement on the roster and tick this property, the position closes by itself.</p>
<form method="post" action="<?= base_url('properties/open') ?>" class="pp-form pp-form--wide pp-panel" id="vacancyForm">
    <?= csrf_field() ?>
    <input type="hidden" name="property" value="<?= esc((string)$property['property_number'], 'attr') ?>">
    <input type="hidden" name="role" value="<?= esc($role, 'attr') ?>">
    <div class="pp-grid">
        <div class="pp-field">
            <label class="pp-label" for="open_since">Open since</label>
            <input class="pp-input" id="open_since" name="open_since" type="date" value="<?= esc((string)$v['open_since'], 'attr') ?>" required>
            <?= $err('open_since') ?>
        </div>
        <div class="pp-field pp-field--2">
            <label class="pp-label" for="coverage">Who is covering in the meantime</label>
            <input class="pp-input" id="coverage" name="coverage" value="<?= esc((string)$v['coverage'], 'attr') ?>" maxlength="255" placeholder="Name, or how the property is covered">
            <?= $err('coverage') ?>
        </div>
    </div>
    <div class="pp-actions pp-actions--form">
        <button type="submit" class="pp-btn pp-btn--primary" id="saveVacancy"><?= $vacancy ? 'Save' : 'Record as open' ?></button>
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('roster/new?back=properties') ?>">Add the new person instead</a>
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('properties') ?>">Cancel</a>
    </div>
</form>
<?php endif; ?>
<?= $this->endSection() ?>
