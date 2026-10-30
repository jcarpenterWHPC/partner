<?php
/**
 * @var array      $user
 * @var array|null $org
 * @var bool       $services
 * @var string     $supportMail
 * @var string     $capexUrl
 */
$admin = !empty($user['is_admin']);
$who = $services ? 'service coordinators and supervisors' : 'regional managers, site managers, maintenance technicians and the rest of the team';
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="pp-h1">Welcome, <?= esc(trim(explode(' ', (string)$user['name'])[0]) ?: 'partner') ?></h1>
<?= view('partials/help', ['id' => 'home', 'title' => $admin ? 'How this works' : 'New here? How this works', 'tips' => $admin ? [
    'Partners keep WHPC current on who is assigned to each property. What Assets has on file is loaded into each organization\'s <strong>Staff roster</strong>; partners record changes, and every change is kept with its date and author under <strong>Recent changes</strong>.',
    '<strong>Portal users</strong> is who may sign in, one organization each. <strong>Organizations</strong> is where Assets is synced and service agencies such as LSS are added.',
    'On the roster and Properties pages you pick an organization and can edit on its behalf.',
] : [
    'WHPC needs to know who looks after each of its properties: ' . $who . '. This is where ' . esc($org['name'] ?? 'your organization') . ' keeps that list current, instead of sending spreadsheets.',
    'What WHPC already has on file is loaded. You only record what changes.',
    '<strong>Someone left?</strong> Open them on the Staff roster and enter their end date. Their positions show as OPEN on the Properties page until you add the replacement.',
    '<strong>Someone new?</strong> Staff roster, <em>Add a person</em>, tick the properties they cover. Any OPEN positions they fill close by themselves.',
    '<strong>Position open for a while?</strong> On the Properties page, note who is covering so WHPC knows whom to call.',
    'Questions or something looks wrong: <a href="mailto:' . esc($supportMail, 'attr') . '">' . esc($supportMail) . '</a>.',
]]) ?>
<div class="pp-cards">
    <a class="pp-card" href="<?= esc($capexUrl, 'attr') ?>" id="cardCapex">
        <span class="pp-card__icon"><i class="bx bx-building-house"></i></span>
        <span class="pp-card__title">CapEx</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
        <span class="pp-card__desc">Capital expenditure requests and approvals.</span>
    </a>
    <a class="pp-card" href="<?= base_url('roster') ?>" id="cardRoster">
        <span class="pp-card__icon"><i class="bx bx-id-card"></i></span>
        <span class="pp-card__title">Staff roster</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
        <span class="pp-card__desc">Your people, one row each, with the properties they cover. Add new hires and record departures here.</span>
    </a>
    <a class="pp-card" href="<?= base_url('properties') ?>" id="cardProperties">
        <span class="pp-card__icon"><i class="bx bx-building-house"></i></span>
        <span class="pp-card__title">Properties</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
        <span class="pp-card__desc">The same people by property: each role filled or OPEN. Note who is covering an open position.</span>
    </a>
    <?php if (!empty($user['is_admin'])): ?>
    <a class="pp-card" href="<?= base_url('admin/users') ?>" id="cardUsers">
        <span class="pp-card__icon"><i class="bx bx-user-plus"></i></span>
        <span class="pp-card__title">Portal users</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
        <span class="pp-card__desc">Who may sign in, and for which organization.</span>
    </a>
    <a class="pp-card" href="<?= base_url('admin/pmcs') ?>" id="cardPmcs">
        <span class="pp-card__icon"><i class="bx bx-buildings"></i></span>
        <span class="pp-card__title">Organizations</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
        <span class="pp-card__desc">Management companies and agencies; sync from Assets.</span>
    </a>
    <a class="pp-card" href="<?= base_url('admin/changes') ?>" id="cardChanges">
        <span class="pp-card__icon"><i class="bx bx-history"></i></span>
        <span class="pp-card__title">Recent changes</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
        <span class="pp-card__desc">Everything partners have entered, newest first.</span>
    </a>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
