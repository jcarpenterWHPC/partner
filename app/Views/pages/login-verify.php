<?php
/**
 * @var string $maskedEmail
 * @var int    $codeLength
 * @var int    $ttlMinutes
 * @var int    $resendWait
 * @var int    $resendsLeft
 * @var string $supportMail
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter your code · WHPC Partner Portal</title>
    <link rel="icon" href="<?= asset('images/favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= asset('images/favicon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= asset('images/apple-touch-icon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="<?= asset('css/whpc-brand.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/partner.css') ?>" rel="stylesheet">
</head>
<body class="whpc-login-body">
    <div class="whpc-login">
        <aside class="whpc-login__brand">
            <img class="whpc-login__keyhole" src="<?= asset('images/brand/keyhole-white.svg') ?>" alt="" aria-hidden="true">
            <img class="whpc-login__brand-logo" src="<?= asset('images/brand/whpc-logo-white.svg') ?>" alt="Wisconsin Housing Preservation Corp.">
            <div class="whpc-login__brand-copy">
                <span class="whpc-login__eyebrow"><i class="bx bx-envelope"></i> Check your email</span>
                <h1 class="whpc-login__headline">Your code is on its&nbsp;way.</h1>
                <p class="whpc-login__mission">Type the code below, or open the link in the same email.</p>
            </div>
            <div class="whpc-login__brand-footer">&copy; <?= date('Y') ?> Wisconsin Housing Preservation Corp.</div>
        </aside>
        <main class="whpc-login__panel">
            <div class="whpc-login__card">
                <img class="whpc-login__card-logo" src="<?= asset('images/brand/whpc-logo.svg') ?>" alt="WHPC">
                <h2 class="whpc-login__title">Enter your code</h2>
                <p class="whpc-login__subtitle">We sent a <?= (int)$codeLength ?>-digit code to <strong><?= esc($maskedEmail) ?></strong>. It expires in <?= (int)$ttlMinutes ?> minutes.</p>
                <?php if (session()->getFlashdata('info')): ?>
                    <div class="whpc-alert whpc-alert--info" role="status"><i class="bx bx-info-circle"></i><span><?= esc(session()->getFlashdata('info')) ?></span></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
                <?php endif; ?>
                <form method="post" action="<?= base_url('login/verify') ?>" class="pp-form" id="verifyForm">
                    <?= csrf_field() ?>
                    <label class="pp-label" for="code">One-time code</label>
                    <input class="pp-input pp-input--code" type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="<?= (int)$codeLength ?>" required autofocus>
                    <button type="submit" class="pp-btn pp-btn--primary pp-btn--block">Sign in</button>
                </form>
                <form method="post" action="<?= base_url('login/resend') ?>" class="pp-form pp-form--inline" id="resendForm">
                    <?= csrf_field() ?>
                    <button type="submit" class="pp-btn pp-btn--ghost" id="resendBtn" <?= $resendWait > 0 || $resendsLeft < 1 ? 'disabled' : '' ?>>
                        Send a new code<?= $resendWait > 0 ? ' (' . (int)$resendWait . 's)' : '' ?>
                    </button>
                    <a class="pp-link" href="<?= base_url('login') ?>">Use a different email</a>
                </form>
                <div class="whpc-login__help">
                    Trouble signing in? Contact <a href="mailto:<?= esc($supportMail, 'attr') ?>"><?= esc($supportMail) ?></a>.
                </div>
            </div>
        </main>
    </div>
    <?php if ($resendWait > 0): ?>
    <script>
    (function () {
        var btn = document.getElementById('resendBtn'), left = <?= (int)$resendWait ?>;
        var t = setInterval(function () {
            left -= 1;
            if (left <= 0) { clearInterval(t); btn.disabled = false; btn.textContent = 'Send a new code'; return; }
            btn.textContent = 'Send a new code (' + left + 's)';
        }, 1000);
    })();
    </script>
    <?php endif; ?>
</body>
</html>
