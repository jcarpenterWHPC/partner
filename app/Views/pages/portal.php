<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="pp-h1">Welcome, <?= esc(trim(explode(' ', (string)$user['name'])[0]) ?: 'partner') ?></h1>
<div class="pp-cards">
    <a class="pp-card" href="<?= esc($capexUrl, 'attr') ?>" id="cardCapex">
        <span class="pp-card__icon"><i class="bx bx-building-house"></i></span>
        <span class="pp-card__title">CapEx</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
    </a>
    <a class="pp-card" href="<?= base_url('roster') ?>" id="cardRoster">
        <span class="pp-card__icon"><i class="bx bx-id-card"></i></span>
        <span class="pp-card__title">Staff roster</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
    </a>
    <?php if (!empty($user['is_admin'])): ?>
    <a class="pp-card" href="<?= base_url('admin/users') ?>" id="cardUsers">
        <span class="pp-card__icon"><i class="bx bx-user-plus"></i></span>
        <span class="pp-card__title">Portal users</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
    </a>
    <a class="pp-card" href="<?= base_url('admin/pmcs') ?>" id="cardPmcs">
        <span class="pp-card__icon"><i class="bx bx-buildings"></i></span>
        <span class="pp-card__title">Management companies</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
    </a>
    <a class="pp-card" href="<?= base_url('admin/changes') ?>" id="cardChanges">
        <span class="pp-card__icon"><i class="bx bx-history"></i></span>
        <span class="pp-card__title">Recent changes</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
    </a>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
