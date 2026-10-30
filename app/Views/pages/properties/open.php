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
$addUrl = base_url('roster/new?' . http_build_query(['back' => 'properties', 'position' => $role, 'property' => (string)$property['property_number']]));
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1"><?= esc($property['name']) ?>: <?= esc($role) ?></h1>
        <p class="pp-sub"><?= esc((string)$property['property_number']) ?><?= $property['city'] ? ' · ' . esc($property['city']) : '' ?> · <?= esc($pmc['name']) ?></p>
    </div>
</div>
<?php if ($holders): ?>
    <div class="pp-panel">
        <p>This position is held by
        <?php foreach ($holders as $i => $s): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= base_url('roster/' . (int)$s['id'] . '/edit?back=properties') ?>"><?= esc($s['name']) ?></a><?php endforeach; ?>.
        If they have left, open their name and enter their end date. If they simply no longer cover this property, untick it on their form. Either way the position then shows as open here.</p>
    </div>
<?php else: ?>
<?php if ($errors): ?>
    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span>Check the highlighted fields.</span></div>
<?php endif; ?>
<p class="pp-intro"><?= $vacancy ? 'This position is recorded as open.' : 'WHPC has nobody on file as the ' . esc($role) . ' for ' . esc($property['name']) . '.' ?> Which is it?</p>
<div class="pp-choices">
    <section class="pp-panel pp-choice">
        <h2 class="pp-choice__title"><i class="bx bx-user-check"></i> Someone does this job</h2>
        <p>They just aren't on the list yet. Add them, with this property and role already filled in, and the gap closes.</p>
        <a class="pp-btn pp-btn--primary" id="addHolder" href="<?= $addUrl ?>"><i class="bx bx-plus"></i> Add the <?= esc($role) ?></a>
        <p class="pp-muted">Already on your roster under another property? Open them on the <a href="<?= base_url('roster') ?>">Staff roster</a> and tick <?= esc($property['name']) ?>.</p>
    </section>
    <section class="pp-panel pp-choice">
        <h2 class="pp-choice__title"><i class="bx bx-time-five"></i> The position is vacant</h2>
        <p>Tell WHPC since when, and who is looking after the property until it is filled. When the replacement is added to the roster, this closes by itself.</p>
        <form method="post" action="<?= base_url('properties/open') ?>" class="pp-form" id="vacancyForm">
            <?= csrf_field() ?>
            <input type="hidden" name="property" value="<?= esc((string)$property['property_number'], 'attr') ?>">
            <input type="hidden" name="role" value="<?= esc($role, 'attr') ?>">
            <div class="pp-field">
                <label class="pp-label" for="open_since">Vacant since</label>
                <input class="pp-input" id="open_since" name="open_since" type="date" value="<?= esc((string)$v['open_since'], 'attr') ?>" required>
                <?= $err('open_since') ?>
            </div>
            <div class="pp-field">
                <label class="pp-label" for="coverage">Who is covering in the meantime</label>
                <input class="pp-input" id="coverage" name="coverage" value="<?= esc((string)$v['coverage'], 'attr') ?>" maxlength="255" placeholder="For example: Sam Smith from Elm Court, two days a week">
                <?= $err('coverage') ?>
            </div>
            <div class="pp-actions pp-actions--form">
                <button type="submit" class="pp-btn pp-btn--primary" id="saveVacancy"><?= $vacancy ? 'Save' : 'Mark as vacant' ?></button>
                <a class="pp-btn pp-btn--ghost" href="<?= base_url('properties') ?>">Cancel</a>
            </div>
        </form>
    </section>
</div>
<?php endif; ?>
<?= $this->endSection() ?>
