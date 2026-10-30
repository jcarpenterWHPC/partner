<?php
/**
 * A page's "How this works" panel: open for a newcomer, remembered as closed once they
 * say "Got it" (per browser, see layouts/main.php). Tips are our own HTML.
 *
 * @var string       $id
 * @var string       $title
 * @var list<string> $tips
 */
?>
<details class="pp-help" data-help="<?= esc($id, 'attr') ?>" open>
    <summary><i class="bx bx-info-circle"></i> <?= esc($title) ?></summary>
    <ul class="pp-help__list">
        <?php foreach ($tips as $tip): ?><li><?= $tip ?></li><?php endforeach; ?>
    </ul>
    <div class="pp-help__foot">
        <a href="<?= base_url('help') ?>">Full guide</a>
        <button type="button" class="pp-btn pp-btn--ghost pp-help__done">Got it, hide this</button>
    </div>
</details>
