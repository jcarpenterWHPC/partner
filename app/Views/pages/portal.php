<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="pp-h1">Welcome, <?= esc(trim(explode(' ', (string)$user['name'])[0]) ?: 'partner') ?></h1>
<div class="pp-cards">
    <a class="pp-card" href="<?= esc($capexUrl, 'attr') ?>" id="cardCapex">
        <span class="pp-card__icon"><i class="bx bx-building-house"></i></span>
        <span class="pp-card__title">CapEx</span>
        <span class="pp-card__go"><i class="bx bx-right-arrow-alt"></i></span>
    </a>
    <div class="pp-card pp-card--soon" id="cardPersonnel" aria-disabled="true">
        <span class="pp-card__icon"><i class="bx bx-id-card"></i></span>
        <span class="pp-card__title">Personnel changes</span>
        <span class="pp-badge">Coming soon</span>
    </div>
</div>
<?= $this->endSection() ?>
