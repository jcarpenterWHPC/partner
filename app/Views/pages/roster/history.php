<?php
/**
 * @var array       $pmc
 * @var array       $person
 * @var list<array> $entries   the PMC's recent changes; filtered to this person below
 * @var array<string,string> $names
 */
$mine = array_values(array_filter($entries, static fn(array $e): bool => (int)$e['staff_id'] === (int)$person['id']));
$label = static fn(string $f): string => ucfirst(str_replace('_', ' ', $f));
$show = static function ($v) use ($names): string {
    if (is_array($v)) {
        $n = array_map(static fn(string $k): string => $names[$k] ?? $k, array_map('strval', $v));
        sort($n);
        return $n ? implode(', ', $n) : '—';
    }
    return $v === null || $v === '' ? '—' : (string)$v;
};
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="pp-pagehead">
    <div>
        <h1 class="pp-h1"><?= esc($person['name']) ?></h1>
        <p class="pp-sub"><?= esc($pmc['name']) ?> · <?= esc($person['position']) ?></p>
    </div>
    <div class="pp-actions">
        <a class="pp-btn pp-btn--ghost" href="<?= base_url('roster/' . (int)$person['id'] . '/edit') ?>">Edit</a>
    </div>
</div>
<ol class="pp-timeline" id="historyList">
<?php foreach ($mine as $e): $c = json_decode((string)$e['changes'], true) ?: []; ?>
    <li class="pp-timeline__item" data-action="<?= esc($e['action'], 'attr') ?>">
        <div class="pp-timeline__head">
            <span class="pp-badge pp-badge--<?= esc($e['action'], 'attr') ?>"><?= esc(ucfirst($e['action'])) ?></span>
            <span class="pp-muted"><?= esc($e['created_at']) ?> · <?= esc($e['user_name'] ?: 'system') ?></span>
        </div>
        <?php if ($e['action'] === 'added' || $e['action'] === 'imported'): ?>
            <dl class="pp-dl">
            <?php foreach ($c as $f => $v): if ($show($v) === '—') continue; ?>
                <dt><?= esc($label($f)) ?></dt><dd><?= esc($show($v)) ?></dd>
            <?php endforeach; ?>
            </dl>
        <?php else: ?>
            <dl class="pp-dl">
            <?php foreach ($c as $f => $d): ?>
                <dt><?= esc($label($f)) ?></dt><dd><span class="pp-from"><?= esc($show($d['from'] ?? null)) ?></span> <i class="bx bx-right-arrow-alt"></i> <?= esc($show($d['to'] ?? null)) ?></dd>
            <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ol>
<?= $this->endSection() ?>
