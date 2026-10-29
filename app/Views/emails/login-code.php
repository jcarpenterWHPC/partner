<?php
/**
 * @var string $name
 * @var string $code
 * @var string $link
 * @var int    $ttlMinutes
 * @var string $supportMail
 */
?>
<p style="margin:0 0 16px;font-size:16px;color:#243746;">Hi <?= esc($name ?: 'there') ?>,</p>
<p style="margin:0 0 20px;font-size:15px;color:#243746;">Here is your one-time code for the WHPC Partner Portal:</p>
<div style="margin:0 0 20px;text-align:center;">
    <span style="display:inline-block;padding:14px 28px;background:#f2f5f7;border:1px solid #d8e0e6;border-radius:10px;font-family:'Source Sans 3',Helvetica,Arial,sans-serif;font-size:34px;font-weight:700;letter-spacing:10px;color:#243746;"><?= esc($code) ?></span>
</div>
<div style="margin:0 0 20px;text-align:center;">
    <a href="<?= esc($link, 'attr') ?>" style="display:inline-block;padding:12px 24px;background:#4E738A;color:#ffffff;border-radius:8px;font-family:'Source Sans 3',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;text-decoration:none;">Or sign in with one click</a>
</div>
<p style="margin:0 0 20px;font-size:14px;color:#4E738A;text-align:center;">The code and the link expire in <?= (int)$ttlMinutes ?> minute<?= (int)$ttlMinutes === 1 ? '' : 's' ?> and work once.</p>
<p style="margin:0 0 8px;font-size:13px;color:#666;">If you didn't just try to sign in, you can ignore this email. Questions: <a href="mailto:<?= esc($supportMail, 'attr') ?>" style="color:#4E738A;"><?= esc($supportMail) ?></a>.</p>
